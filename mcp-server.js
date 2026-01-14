#!/usr/bin/env node

/**
 * MCP Server for Documentation Collaborative Tool
 *
 * This server exposes the REST API as MCP tools for Claude Code integration.
 *
 * Usage: node mcp-server.js --base-url=https://localhost
 */

const { Server } = require('@modelcontextprotocol/sdk/server/index.js');
const { StdioServerTransport } = require('@modelcontextprotocol/sdk/server/stdio.js');
const {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} = require('@modelcontextprotocol/sdk/types.js');

// Parse command line arguments
const args = process.argv.slice(2);
let baseUrl = 'https://localhost';
for (const arg of args) {
  if (arg.startsWith('--base-url=')) {
    baseUrl = arg.split('=')[1];
  }
}

// State
let sessionId = null;
let participantId = null;

// HTTP client with SSL bypass for development
const https = require('https');
const http = require('http');

const agent = new https.Agent({ rejectUnauthorized: false });

async function apiCall(method, path, body = null, extraHeaders = {}) {
  const url = new URL(path, baseUrl);
  const isHttps = url.protocol === 'https:';
  const lib = isHttps ? https : http;

  return new Promise((resolve, reject) => {
    const options = {
      hostname: url.hostname,
      port: url.port || (isHttps ? 443 : 80),
      path: url.pathname + url.search,
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...extraHeaders,
      },
      agent: isHttps ? agent : undefined,
    };

    const req = lib.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, data: JSON.parse(data) });
        } catch (e) {
          resolve({ status: res.statusCode, data: data });
        }
      });
    });

    req.on('error', reject);

    if (body) {
      req.write(JSON.stringify(body));
    }
    req.end();
  });
}

// Create server
const server = new Server(
  {
    name: 'documentation-collaborative',
    version: '1.0.0',
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

// Define tools
const tools = [
  {
    name: 'list_sessions',
    description: 'List all existing documentation sessions',
    inputSchema: {
      type: 'object',
      properties: {},
    },
  },
  {
    name: 'create_session',
    description: 'Create a new documentation session and join as an AI agent. Returns session info and invite code for users.',
    inputSchema: {
      type: 'object',
      properties: {
        title: { type: 'string', description: 'Session title' },
        description: { type: 'string', description: 'Session description (optional)' },
        agent_name: { type: 'string', description: 'Name for the AI agent (default: Claude Assistant)' },
      },
      required: ['title'],
    },
  },
  {
    name: 'join_session',
    description: 'Join an existing session using an invite code',
    inputSchema: {
      type: 'object',
      properties: {
        invite_code: { type: 'string', description: 'The session invite code' },
        agent_name: { type: 'string', description: 'Name for the AI agent' },
      },
      required: ['invite_code'],
    },
  },
  {
    name: 'get_session_status',
    description: 'Get current session status including documents, annotations, and participants',
    inputSchema: {
      type: 'object',
      properties: {},
    },
  },
  {
    name: 'list_documents',
    description: 'List all documents in the current session',
    inputSchema: {
      type: 'object',
      properties: {},
    },
  },
  {
    name: 'read_document',
    description: 'Read a document content by ID',
    inputSchema: {
      type: 'object',
      properties: {
        document_id: { type: 'string', description: 'Document UUID' },
      },
      required: ['document_id'],
    },
  },
  {
    name: 'create_document',
    description: 'Create a new document in the session',
    inputSchema: {
      type: 'object',
      properties: {
        title: { type: 'string', description: 'Document title' },
        content: { type: 'string', description: 'Document content in Markdown' },
        type: {
          type: 'string',
          description: 'Document type',
          enum: ['general', 'synthesis', 'question', 'comparison', 'annexe', 'compte_rendu']
        },
        parent_id: { type: 'string', description: 'Parent document UUID (optional)' },
      },
      required: ['title', 'content'],
    },
  },
  {
    name: 'update_document',
    description: 'Update an existing document',
    inputSchema: {
      type: 'object',
      properties: {
        document_id: { type: 'string', description: 'Document UUID' },
        title: { type: 'string', description: 'New title (optional)' },
        content: { type: 'string', description: 'New content in Markdown (optional)' },
        type: { type: 'string', description: 'New type (optional)' },
      },
      required: ['document_id'],
    },
  },
  {
    name: 'delete_document',
    description: 'Delete a document and all its children. Use with caution - this cannot be undone.',
    inputSchema: {
      type: 'object',
      properties: {
        document_id: { type: 'string', description: 'Document UUID to delete' },
      },
      required: ['document_id'],
    },
  },
  {
    name: 'list_annotations',
    description: 'List annotations for a document or the entire session',
    inputSchema: {
      type: 'object',
      properties: {
        document_id: { type: 'string', description: 'Filter by document UUID (optional)' },
        type: {
          type: 'string',
          description: 'Filter by type',
          enum: ['question', 'objection', 'suggestion', 'comment', 'validation']
        },
        status: {
          type: 'string',
          description: 'Filter by status',
          enum: ['open', 'resolved', 'acknowledged']
        },
      },
    },
  },
  {
    name: 'respond_to_annotation',
    description: 'Respond to an annotation (question, objection, etc.)',
    inputSchema: {
      type: 'object',
      properties: {
        annotation_id: { type: 'string', description: 'Annotation UUID' },
        content: { type: 'string', description: 'Response content' },
      },
      required: ['annotation_id', 'content'],
    },
  },
  {
    name: 'resolve_annotation',
    description: 'Mark an annotation as resolved',
    inputSchema: {
      type: 'object',
      properties: {
        annotation_id: { type: 'string', description: 'Annotation UUID' },
      },
      required: ['annotation_id'],
    },
  },
  {
    name: 'delete_annotation',
    description: 'Delete an annotation that is no longer needed. Use this to clean up resolved annotations that have become obsolete.',
    inputSchema: {
      type: 'object',
      properties: {
        annotation_id: { type: 'string', description: 'Annotation UUID to delete' },
      },
      required: ['annotation_id'],
    },
  },
  {
    name: 'create_decision',
    description: 'Create a decision point with options for users to vote on. Use this when presenting technical choices that need team arbitration.',
    inputSchema: {
      type: 'object',
      properties: {
        title: { type: 'string', description: 'Decision title (e.g., "Restriction d\'accès - Event Subscriber vs Surcharge Controller")' },
        description: { type: 'string', description: 'Context or explanation for the decision' },
        options: {
          type: 'array',
          description: 'List of options (2-4 options recommended)',
          items: {
            type: 'object',
            properties: {
              label: { type: 'string', description: 'Option label (e.g., "Option A: Event Subscriber")' },
              description: { type: 'string', description: 'Detailed description of this option' },
            },
            required: ['label'],
          },
        },
        document_id: { type: 'string', description: 'Link to a specific document UUID (optional)' },
      },
      required: ['title', 'options'],
    },
  },
  {
    name: 'list_decisions',
    description: 'List all decisions in the session, optionally filtered by document or status',
    inputSchema: {
      type: 'object',
      properties: {
        document_id: { type: 'string', description: 'Filter by document UUID (optional)' },
        status: {
          type: 'string',
          description: 'Filter by status',
          enum: ['ouvert', 'en_discussion', 'consensus', 'valide', 'reporte'],
        },
      },
    },
  },
  {
    name: 'delete_decision',
    description: 'Delete a decision. Use with caution - this cannot be undone. All votes will also be deleted.',
    inputSchema: {
      type: 'object',
      properties: {
        decision_id: { type: 'string', description: 'Decision UUID to delete' },
      },
      required: ['decision_id'],
    },
  },
  {
    name: 'get_arbitrations',
    description: 'Get all validated arbitrations (decisions that have been voted and validated by the team). Use this to generate a summary of technical choices made.',
    inputSchema: {
      type: 'object',
      properties: {},
    },
  },
  {
    name: 'update_session_status',
    description: 'Update the session status. IMPORTANT: Use "en_cours" when you start working on a session, "termine" when all work is done.',
    inputSchema: {
      type: 'object',
      properties: {
        status: {
          type: 'string',
          description: 'New status for the session',
          enum: ['preparation', 'en_cours', 'termine', 'archive']
        },
      },
      required: ['status'],
    },
  },
  {
    name: 'list_estimations',
    description: 'List all Planning Poker estimations in the current session. Use to see pending estimations that need votes.',
    inputSchema: {
      type: 'object',
      properties: {
        status: {
          type: 'string',
          description: 'Filter by status (optional)',
          enum: ['open', 'revealed']
        },
        document_id: { type: 'string', description: 'Filter by linked document UUID (optional)' },
      },
    },
  },
  {
    name: 'create_estimation',
    description: 'Create a new Planning Poker estimation for team effort sizing. Participants vote using Fibonacci values.',
    inputSchema: {
      type: 'object',
      properties: {
        title: { type: 'string', description: 'Estimation title (e.g., "Implementation of feature X")' },
        description: { type: 'string', description: 'Description or context for the estimation (optional)' },
        document_id: { type: 'string', description: 'Link to a document UUID (optional)' },
      },
      required: ['title'],
    },
  },
  {
    name: 'read_estimation',
    description: 'Get details of a specific estimation including votes (if revealed)',
    inputSchema: {
      type: 'object',
      properties: {
        estimation_id: { type: 'string', description: 'Estimation UUID' },
      },
      required: ['estimation_id'],
    },
  },
  {
    name: 'vote_estimation',
    description: 'Cast a vote on a Planning Poker estimation using Fibonacci values (0, 1, 2, 3, 5, 8, 13, 21, or ? for uncertainty)',
    inputSchema: {
      type: 'object',
      properties: {
        estimation_id: { type: 'string', description: 'Estimation UUID' },
        value: {
          type: 'string',
          description: 'Fibonacci vote value',
          enum: ['0', '1', '2', '3', '5', '8', '13', '21', '?']
        },
      },
      required: ['estimation_id', 'value'],
    },
  },
  {
    name: 'reveal_estimation',
    description: 'Reveal all votes for an estimation. Use after all participants have voted.',
    inputSchema: {
      type: 'object',
      properties: {
        estimation_id: { type: 'string', description: 'Estimation UUID' },
      },
      required: ['estimation_id'],
    },
  },
];

// List tools handler
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return { tools };
});

// Call tool handler
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    switch (name) {
      case 'list_sessions': {
        const response = await apiCall('GET', '/api/sessions');
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'create_session': {
        const response = await apiCall('POST', '/api/sessions/agent/create', {
          title: args.title,
          description: args.description,
          agent_name: args.agent_name || 'Claude Assistant',
        });

        if (response.status === 201) {
          sessionId = response.data.session.id;
          participantId = response.data.agent.participant_id;
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                session_id: sessionId,
                participant_id: participantId,
                invite_code: response.data.session.invite_code,
                invite_url: `${baseUrl}/?code=${response.data.session.invite_code}`,
                message: `Session "${args.title}" created. Share this invite code with users: ${response.data.session.invite_code}`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data.error || 'Failed to create session');
      }

      case 'join_session': {
        const response = await apiCall('POST', `/api/sessions/join/${args.invite_code}`, {
          pseudo: args.agent_name || 'Claude Assistant',
          is_agent: true,
        });

        if (response.status === 200) {
          sessionId = response.data.session.id;
          participantId = response.data.participant.id;
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                session_id: sessionId,
                participant_id: participantId,
                session_title: response.data.session.title,
                message: `Joined session "${response.data.session.title}"`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data.error || 'Failed to join session');
      }

      case 'get_session_status': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('GET', `/api/mcp/sessions/${sessionId}/status`);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'list_documents': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('GET', `/api/mcp/sessions/${sessionId}/documents`);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'read_document': {
        const response = await apiCall('GET', `/api/mcp/documents/${args.document_id}`);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'create_document': {
        if (!sessionId || !participantId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('POST', `/api/mcp/sessions/${sessionId}/documents`, {
          title: args.title,
          content: args.content,
          type: args.type || 'general',
          parent_id: args.parent_id,
          participant_id: participantId,
        });

        if (response.status === 201) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                document: response.data,
                view_url: `${baseUrl}/session/${sessionId}/document/${response.data.slug}`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data.error || 'Failed to create document');
      }

      case 'update_document': {
        if (!participantId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const body = { participant_id: participantId };
        if (args.title) body.title = args.title;
        if (args.content) body.content = args.content;
        if (args.type) body.type = args.type;

        const response = await apiCall('PUT', `/api/mcp/documents/${args.document_id}`, body);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'delete_document': {
        if (!participantId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('DELETE', `/api/mcp/documents/${args.document_id}`);

        if (response.status === 204 || response.status === 200) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                message: `Document ${args.document_id} has been deleted.`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data?.error || 'Failed to delete document');
      }

      case 'list_annotations': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }

        let url = `/api/mcp/sessions/${sessionId}/annotations`;
        const params = new URLSearchParams();
        if (args.document_id) params.append('document_id', args.document_id);
        if (args.type) params.append('type', args.type);
        if (args.status) params.append('status', args.status);
        if (params.toString()) url += '?' + params.toString();

        const response = await apiCall('GET', url);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'respond_to_annotation': {
        if (!participantId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('POST', `/api/annotations/${args.annotation_id}/replies`, {
          participant_id: participantId,
          content: args.content,
        });
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'resolve_annotation': {
        if (!participantId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('POST', `/api/annotations/${args.annotation_id}/resolve`, {
          participant_id: participantId,
        });
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'delete_annotation': {
        const response = await apiCall('DELETE', `/api/mcp/annotations/${args.annotation_id}`);

        if (response.status === 204 || response.status === 200) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                message: `Annotation ${args.annotation_id} has been deleted.`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data?.error || 'Failed to delete annotation');
      }

      case 'create_decision': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('POST', '/api/decisions', {
          session_id: sessionId,
          title: args.title,
          description: args.description,
          options: args.options,
          document_id: args.document_id,
        });

        if (response.status === 201) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                decision: response.data,
                message: `Decision point "${args.title}" created with ${args.options.length} options. Users can now vote on it.`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data.error || 'Failed to create decision');
      }

      case 'list_decisions': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }

        let url = `/api/decisions/session/${sessionId}`;
        const params = new URLSearchParams();
        if (args.document_id) params.append('document_id', args.document_id);
        if (args.status) params.append('status', args.status);
        if (params.toString()) url += '?' + params.toString();

        const response = await apiCall('GET', url);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'delete_decision': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('DELETE', `/api/decisions/${args.decision_id}`);

        if (response.status === 200) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                message: `Decision ${args.decision_id} has been deleted.`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data?.error || 'Failed to delete decision');
      }

      case 'get_arbitrations': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('GET', `/api/decisions/session/${sessionId}/arbitrations`);

        const arbitrations = response.data;
        let summary = `## Arbitrages validés (${arbitrations.length})\n\n`;

        if (arbitrations.length === 0) {
          summary += '_Aucun arbitrage validé pour le moment._\n';
        } else {
          arbitrations.forEach((arb, i) => {
            summary += `### ${i + 1}. ${arb.title}\n`;
            if (arb.description) {
              summary += `${arb.description}\n`;
            }
            summary += `**Choix retenu:** ${arb.selected_option?.label || 'N/A'}\n`;
            if (arb.selected_option?.description) {
              summary += `> ${arb.selected_option.description}\n`;
            }
            summary += `_${arb.vote_count} vote(s) - Validé le ${new Date(arb.validated_at).toLocaleDateString('fr-FR')}_\n\n`;
          });
        }

        return {
          content: [{
            type: 'text',
            text: JSON.stringify({
              arbitrations: response.data,
              summary_markdown: summary,
            }, null, 2),
          }],
        };
      }

      case 'update_session_status': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('PATCH', `/api/mcp/sessions/${sessionId}/status`, {
          status: args.status,
        });

        if (response.status === 200) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                session: response.data,
                message: `Session status updated to "${args.status}"`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data?.error || 'Failed to update session status');
      }

      case 'list_estimations': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }

        let url = `/api/mcp/sessions/${sessionId}/estimations`;
        const params = new URLSearchParams();
        if (args.status) params.append('status', args.status);
        if (args.document_id) params.append('document_id', args.document_id);
        if (params.toString()) url += '?' + params.toString();

        const response = await apiCall('GET', url);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'create_estimation': {
        if (!sessionId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('POST', `/api/mcp/sessions/${sessionId}/estimations`, {
          title: args.title,
          description: args.description,
          document_id: args.document_id,
        });

        if (response.status === 201) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                estimation: response.data,
                message: `Estimation "${args.title}" created. Participants can now vote using Fibonacci values.`,
                fibonacci_values: ['0', '1', '2', '3', '5', '8', '13', '21', '?'],
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data?.error || 'Failed to create estimation');
      }

      case 'read_estimation': {
        const response = await apiCall('GET', `/api/mcp/estimations/${args.estimation_id}`);
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(response.data, null, 2),
          }],
        };
      }

      case 'vote_estimation': {
        if (!participantId) {
          throw new Error('No active session. Use create_session or join_session first.');
        }
        const response = await apiCall('POST', `/api/mcp/estimations/${args.estimation_id}/vote`, {
          value: args.value,
        }, { 'X-Agent-Id': participantId });

        if (response.status === 200) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                estimation: response.data.estimation,
                voted: true,
                message: `Vote "${args.value}" recorded.`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data?.error || 'Failed to vote on estimation');
      }

      case 'reveal_estimation': {
        const response = await apiCall('POST', `/api/mcp/estimations/${args.estimation_id}/reveal`);

        if (response.status === 200) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({
                success: true,
                estimation: response.data,
                message: `Votes revealed. Average: ${response.data.average || 'N/A'}`,
              }, null, 2),
            }],
          };
        }
        throw new Error(response.data?.error || 'Failed to reveal estimation');
      }

      default:
        throw new Error(`Unknown tool: ${name}`);
    }
  } catch (error) {
    return {
      content: [{
        type: 'text',
        text: JSON.stringify({ error: error.message }, null, 2),
      }],
      isError: true,
    };
  }
});

// Start server
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('Documentation Collaborative MCP Server running');
  console.error(`Base URL: ${baseUrl}`);
}

main().catch(console.error);
