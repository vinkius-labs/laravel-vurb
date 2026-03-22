/**
 * Schema Manifest types — matches the JSON produced by ManifestCompiler.php
 */

export interface SchemaManifest {
  version: string;
  server: ServerConfig;
  bridge: BridgeConfig;
  toolExposition: 'flat' | 'grouped';
  tools: Record<string, ManifestTool[]>;
  presenters: Record<string, ManifestPresenter>;
  models: Record<string, ManifestModel>;
  stateSync: StateSyncConfig;
  fsm: FsmConfig | null;
  skills: unknown[];
}

export interface ServerConfig {
  name: string;
  version: string;
  description: string;
}

export interface BridgeConfig {
  baseUrl: string;
  prefix: string;
  token: string;
}

export interface ManifestTool {
  name: string;
  description: string;
  instructions?: string;
  inputSchema: JsonSchema;
  annotations: ToolAnnotations;
  middleware: string[];
  tags: string[];
  stateSync?: StateSyncPolicy;
  fsmBind?: FsmBindConfig;
  concurrency?: number;
}

export interface JsonSchema {
  type: 'object';
  properties: Record<string, JsonSchemaProperty>;
  required: string[];
}

export interface JsonSchemaProperty {
  type: string;
  description?: string;
  example?: unknown;
  enum?: (string | number)[];
  items?: JsonSchemaProperty;
  properties?: Record<string, JsonSchemaProperty>;
  required?: string[];
}

export interface ToolAnnotations {
  verb: 'query' | 'mutation' | 'action';
  presenter?: string;
  hidden?: boolean;
  tags?: string[];
  [key: string]: unknown;
}

export interface ManifestPresenter {
  class: string;
  isVurbPresenter: boolean;
  agentLimit?: {
    max: number;
    warningMessage: string;
  };
  fields: string[];
}

export interface ManifestModel {
  class: string;
  table: string;
  schema: Record<string, ModelFieldSchema>;
}

export interface ModelFieldSchema {
  type: string;
  description?: string;
  enum?: string[];
}

export interface StateSyncConfig {
  default: string;
  policies: Record<string, StateSyncPolicy>;
}

export interface StateSyncPolicy {
  directive?: string;
  invalidates?: string[];
}

export interface FsmConfig {
  id: string;
  initial: string;
  states: Record<string, FsmStateConfig>;
  store: 'cache' | 'database';
}

export interface FsmStateConfig {
  events: Record<string, string>;
}

export interface FsmBindConfig {
  states: string[];
  event?: string;
}
