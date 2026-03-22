import type { BridgeConfig, ManifestTool } from './types.js';

/**
 * Creates a handler function that proxies tool calls to the Laravel bridge.
 */
export function createProxyHandler(
  tool: ManifestTool,
  bridge: BridgeConfig,
) {
  return async (input: Record<string, unknown>): Promise<unknown> => {
    const url = `${bridge.baseUrl}${bridge.prefix}/execute/${encodeURIComponent(tool.name)}/handle`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Vurb-Token': bridge.token,
      },
      body: JSON.stringify(input),
      signal: AbortSignal.timeout(30_000),
    });

    if (!response.ok) {
      const body = await response.text();
      let parsed: Record<string, unknown> = {};
      try {
        parsed = JSON.parse(body);
      } catch {
        // non-JSON response
      }

      return {
        isError: true,
        content: [{
          type: 'text',
          text: JSON.stringify({
            error: true,
            code: (parsed as Record<string, unknown>).code ?? 'BRIDGE_ERROR',
            message: (parsed as Record<string, unknown>).message ?? `Bridge returned ${response.status}`,
          }),
        }],
      };
    }

    const data = await response.json() as Record<string, unknown>;

    // Build MCP tool response
    const result: Record<string, unknown> = {
      content: [{
        type: 'text',
        text: JSON.stringify(data.data ?? data),
      }],
    };

    // Attach system rules as additional text content if present
    const systemRules = data.systemRules as string[] | undefined;
    if (systemRules && systemRules.length > 0) {
      result.content = [
        {
          type: 'text',
          text: `[System Rules]\n${systemRules.join('\n')}`,
        },
        ...(result.content as unknown[]),
      ];
    }

    // Attach UI blocks as resource annotations
    const uiBlocks = data.uiBlocks as unknown[] | undefined;
    if (uiBlocks && uiBlocks.length > 0) {
      (result.content as unknown[]).push({
        type: 'text',
        text: `[UI Blocks]\n${JSON.stringify(uiBlocks)}`,
      });
    }

    // Attach suggested actions
    const suggestActions = data.suggestActions as unknown[] | undefined;
    if (suggestActions && suggestActions.length > 0) {
      (result.content as unknown[]).push({
        type: 'text',
        text: `[Suggested Actions]\n${JSON.stringify(suggestActions)}`,
      });
    }

    return result;
  };
}

/**
 * Build a JSON schema-compatible parameter definition for the vurb-ts fluent builder.
 * Converts the manifest's inputSchema to the format expected by defineTool params.
 */
export function buildParamDefs(
  tool: ManifestTool,
): Record<string, unknown> {
  const params: Record<string, unknown> = {};
  const properties = tool.inputSchema?.properties ?? {};
  const required = tool.inputSchema?.required ?? [];

  for (const [name, schema] of Object.entries(properties)) {
    const isRequired = required.includes(name);
    const paramDef: Record<string, unknown> = {
      type: schema.type ?? 'string',
      optional: !isRequired,
    };

    if (schema.description) {
      paramDef.description = schema.description;
    }

    if (schema.enum) {
      paramDef.enum = schema.enum;
    }

    if (schema.items) {
      paramDef.array = schema.items.type ?? 'string';
    }

    params[name] = paramDef;
  }

  return params;
}
