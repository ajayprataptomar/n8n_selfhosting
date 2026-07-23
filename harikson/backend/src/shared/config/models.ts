export const HARIKSON_MODELS: Record<
  string,
  { displayName: string; description: string; icon: string; plan: string }
> = {
  'harikson/qwen3-coder:1.5b': {
    displayName: 'Harikson Starter',
    description: 'Fast responses for everyday tasks',
    icon: 'Zap',
    plan: 'STARTER',
  },
  'harikson/qwen3-coder:4b': {
    displayName: 'Harikson Pro',
    description: 'Advanced coding and reasoning',
    icon: 'Code',
    plan: 'PRO',
  },
  'harikson/qwen3-coder:8b': {
    displayName: 'Harikson Business',
    description: 'Complex multi-file projects',
    icon: 'Building',
    plan: 'BUSINESS',
  },
  'harikson/qwen3-coder:14b': {
    displayName: 'Harikson Enterprise',
    description: 'Maximum capability for large teams',
    icon: 'Crown',
    plan: 'ENTERPRISE',
  },
  // Also support default / existing fallback names in database
  'qwen3-coder-4b': {
    displayName: 'Harikson Pro',
    description: 'Advanced coding and reasoning',
    icon: 'Code',
    plan: 'PRO',
  },
  'qwen3-coder-8b': {
    displayName: 'Harikson Business',
    description: 'Complex multi-file projects',
    icon: 'Building',
    plan: 'BUSINESS',
  },
  'qwen3-coder-14b': {
    displayName: 'Harikson Enterprise',
    description: 'Maximum capability for large teams',
    icon: 'Crown',
    plan: 'ENTERPRISE',
  },
};

export function getDisplayModelName(internalName: string): string {
  return HARIKSON_MODELS[internalName]?.displayName || 'Harikson AI';
}

export function sanitizeTenantResponse(tenant: any) {
  const modelName = tenant.model || 'qwen3-coder-8b';
  return {
    tenantId: tenant.id,
    name: tenant.name,
    email: tenant.email,
    plan: tenant.plan,
    aiAgent: getDisplayModelName(modelName),
    aiDescription:
      HARIKSON_MODELS[modelName]?.description || 'Private AI Assistant',
    status: tenant.status,
    subdomain: tenant.name,
    branding: tenant.whiteLabelSettings || {},
  };
}
