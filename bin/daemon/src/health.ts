import type { BridgeConfig } from './types.js';

/**
 * Perform a health check against the Laravel bridge.
 */
export async function checkBridgeHealth(bridge: BridgeConfig): Promise<{
  healthy: boolean;
  latencyMs: number;
  details?: Record<string, unknown>;
}> {
  const start = performance.now();

  try {
    const url = `${bridge.baseUrl}${bridge.prefix}/health`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Vurb-Token': bridge.token,
      },
      signal: AbortSignal.timeout(5_000),
    });

    const latencyMs = performance.now() - start;

    if (!response.ok) {
      return { healthy: false, latencyMs };
    }

    const data = await response.json() as Record<string, unknown>;

    return {
      healthy: true,
      latencyMs,
      details: data,
    };
  } catch {
    return {
      healthy: false,
      latencyMs: performance.now() - start,
    };
  }
}
