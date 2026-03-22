import { readFileSync } from 'node:fs';
import type { SchemaManifest } from './types.js';

/**
 * Load and parse the Schema Manifest JSON from disk.
 */
export function loadManifest(path: string): SchemaManifest {
  const raw = readFileSync(path, 'utf-8');
  const manifest = JSON.parse(raw) as SchemaManifest;

  validateManifest(manifest);

  return manifest;
}

/**
 * Validate that the manifest has the required fields.
 */
function validateManifest(manifest: SchemaManifest): void {
  if (!manifest.version) {
    throw new Error('Manifest missing "version" field.');
  }

  if (!manifest.server?.name) {
    throw new Error('Manifest missing "server.name" field.');
  }

  if (!manifest.bridge?.baseUrl) {
    throw new Error('Manifest missing "bridge.baseUrl" field.');
  }

  if (!manifest.bridge?.token) {
    throw new Error('Manifest missing "bridge.token" field.');
  }

  if (!manifest.tools || typeof manifest.tools !== 'object') {
    throw new Error('Manifest missing "tools" object.');
  }
}

/**
 * Flatten all tools from the namespace-grouped structure into a single array.
 */
export function flattenTools(manifest: SchemaManifest): SchemaManifest['tools'][string] {
  const tools: SchemaManifest['tools'][string] = [];

  for (const namespace of Object.keys(manifest.tools)) {
    tools.push(...manifest.tools[namespace]);
  }

  return tools;
}
