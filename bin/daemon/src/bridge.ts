#!/usr/bin/env node

/**
 * Laravel Vurb Bridge Daemon
 *
 * Reads the Schema Manifest compiled by PHP and registers proxy tools
 * in vurb-ts that forward execution to the Laravel bridge controller.
 *
 * Launched via: npx tsx bin/daemon/src/bridge.ts
 * Environment:
 *   VURB_MANIFEST_PATH  — path to manifest.json
 *   VURB_INTERNAL_TOKEN — shared secret for bridge auth
 *   VURB_BRIDGE_URL     — Laravel app base URL
 *   VURB_TRANSPORT      — stdio | http
 *   VURB_PORT           — port for http transport
 */

import { initVurb, startServer, defineTool, success } from '@vurb/core';
import { loadManifest, flattenTools } from './manifest-loader.js';
import { createProxyHandler, buildParamDefs } from './proxy-builder.js';
import { checkBridgeHealth } from './health.js';
import type { SchemaManifest, ManifestTool, BridgeConfig } from './types.js';

// ─── Configuration ─────────────────────────────────────────────────────────

const MANIFEST_PATH = process.env.VURB_MANIFEST_PATH;
const BRIDGE_URL = process.env.VURB_BRIDGE_URL ?? 'http://127.0.0.1:8000';
const BRIDGE_TOKEN = process.env.VURB_INTERNAL_TOKEN ?? '';
const TRANSPORT = (process.env.VURB_TRANSPORT ?? 'stdio') as 'stdio' | 'http';
const PORT = process.env.VURB_PORT ? parseInt(process.env.VURB_PORT, 10) : 3001;

if (!MANIFEST_PATH) {
  console.error('[vurb-daemon] VURB_MANIFEST_PATH is required.');
  process.exit(1);
}

if (!BRIDGE_TOKEN) {
  console.error('[vurb-daemon] VURB_INTERNAL_TOKEN is required.');
  process.exit(1);
}

// ─── Types ─────────────────────────────────────────────────────────────────

interface BridgeContext {
  bridge: BridgeConfig;
}

// ─── Bootstrap ─────────────────────────────────────────────────────────────

async function main(): Promise<void> {
  console.log('[vurb-daemon] Loading manifest:', MANIFEST_PATH);

  const manifest = loadManifest(MANIFEST_PATH);
  const allTools = flattenTools(manifest);

  console.log(`[vurb-daemon] Server: ${manifest.server.name} v${manifest.server.version}`);
  console.log(`[vurb-daemon] Tools: ${allTools.length} registered`);
  console.log(`[vurb-daemon] Transport: ${TRANSPORT}`);

  const bridge: BridgeConfig = {
    baseUrl: BRIDGE_URL,
    prefix: manifest.bridge.prefix ?? '/_vurb',
    token: BRIDGE_TOKEN,
  };

  // Initialize vurb
  const f = initVurb<BridgeContext>();
  const registry = f.registry();

  // Register all tools from the manifest
  registerTools(f, registry, allTools, bridge);

  // Optional: check bridge health before starting
  const health = await checkBridgeHealth(bridge);
  if (health.healthy) {
    console.log(`[vurb-daemon] Bridge health OK (${Math.round(health.latencyMs)}ms)`);
  } else {
    console.warn(`[vurb-daemon] Bridge health check failed — daemon will start anyway.`);
    console.warn(`[vurb-daemon] Make sure Laravel is running at: ${BRIDGE_URL}`);
  }

  // Start the MCP server
  const serverResult = await startServer({
    name: manifest.server.name,
    version: manifest.server.version,
    registry,
    transport: TRANSPORT,
    ...(TRANSPORT === 'http' ? { port: PORT } : {}),
    contextFactory: () => ({
      bridge,
    }),
  });

  // Signal ready to the parent process (PHP DaemonManager watches for this)
  console.log('VURB_DAEMON_READY');

  // Graceful shutdown
  const shutdown = async () => {
    console.log('[vurb-daemon] Shutting down...');
    await serverResult.close();
    process.exit(0);
  };

  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);
}

// ─── Tool Registration ─────────────────────────────────────────────────────

function registerTools(
  f: ReturnType<typeof initVurb<BridgeContext>>,
  registry: ReturnType<ReturnType<typeof initVurb<BridgeContext>>['registry']>,
  tools: ManifestTool[],
  bridge: BridgeConfig,
): void {
  for (const tool of tools) {
    if (tool.annotations?.hidden) {
      continue;
    }

    const handler = createProxyHandler(tool, bridge);
    const verb = tool.annotations?.verb ?? 'query';

    // Build the tool using the fluent API based on verb type
    const builderFn = verb === 'mutation'
      ? f.mutation.bind(f)
      : verb === 'action'
        ? f.action?.bind(f) ?? f.query.bind(f)
        : f.query.bind(f);

    let builder = builderFn(tool.name).describe(tool.description);

    // Add instructions if present
    if (tool.instructions) {
      // Instructions are passed via description enhancement
      builder = builder.describe(`${tool.description}\n\n[Instructions] ${tool.instructions}`);
    }

    // Register input parameters dynamically
    const properties = tool.inputSchema?.properties ?? {};
    const required = tool.inputSchema?.required ?? [];

    for (const [paramName, schema] of Object.entries(properties)) {
      const isRequired = required.includes(paramName);
      const desc = schema.description ?? paramName;

      builder = addParam(builder, paramName, schema, isRequired, desc);
    }

    // Terminal handler: proxy to Laravel
    const registered = builder.handle(async (input: Record<string, unknown>, ctx: BridgeContext) => {
      const result = await handler(input);
      return success(result);
    });

    registry.register(registered);
  }
}

/**
 * Add a parameter to the tool builder based on JSON Schema type.
 */
function addParam(
  builder: any,
  name: string,
  schema: { type?: string; description?: string; enum?: (string | number)[] },
  isRequired: boolean,
  description: string,
): any {
  const type = schema.type ?? 'string';

  if (schema.enum) {
    // Enum parameter — use withString + constrain
    return isRequired
      ? builder.withString(name, description)
      : builder.withOptionalString(name, description);
  }

  switch (type) {
    case 'string':
      return isRequired
        ? builder.withString(name, description)
        : builder.withOptionalString(name, description);

    case 'integer':
    case 'number':
      return isRequired
        ? builder.withNumber(name, description)
        : builder.withOptionalNumber(name, description);

    case 'boolean':
      return isRequired
        ? builder.withBoolean(name, description)
        : builder.withOptionalBoolean(name, description);

    case 'array':
      // Arrays are passed as JSON strings in MCP text content
      return isRequired
        ? builder.withString(name, `${description} (JSON array)`)
        : builder.withOptionalString(name, `${description} (JSON array)`);

    case 'object':
      // Objects are passed as JSON strings
      return isRequired
        ? builder.withString(name, `${description} (JSON object)`)
        : builder.withOptionalString(name, `${description} (JSON object)`);

    default:
      return isRequired
        ? builder.withString(name, description)
        : builder.withOptionalString(name, description);
  }
}

// ─── Run ────────────────────────────────────────────────────────────────────

main().catch((error) => {
  console.error('[vurb-daemon] Fatal error:', error);
  process.exit(1);
});
