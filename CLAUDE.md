# Documentation MCP - Exchange

Ce document décrit l'API MCP (Model Context Protocol) pour les agents IA travaillant avec l'application Exchange.

## Base URL

```
https://localhost/api/mcp
```

## Gestion des Statuts de Session

### IMPORTANT: Mise à jour obligatoire du statut

L'agent **DOIT** mettre à jour le statut de la session selon son cycle de vie. Le statut par défaut d'une nouvelle session est `preparation`.

### Les 4 statuts possibles

| Statut | Description | Quand l'utiliser |
|--------|-------------|------------------|
| `preparation` | Phase de préparation initiale | État initial - documents en cours d'ajout |
| `en_cours` | Session active | **Dès que l'agent commence à travailler** sur la session |
| `termine` | Session terminée | Quand toutes les annotations sont traitées et les décisions prises |
| `archive` | Session archivée | Pour archiver une session terminée (ne sera plus listée) |

### Transitions recommandées

```
preparation → en_cours → termine → archive
```

### Quand mettre à jour le statut

1. **`preparation` → `en_cours`**:
   - Dès que l'agent rejoint la session et commence à travailler
   - Quand le premier document est créé/modifié par l'agent
   - Quand l'agent répond à la première annotation

2. **`en_cours` → `termine`**:
   - Quand toutes les annotations ont été traitées (aucune annotation `open` restante)
   - Quand toutes les décisions ont atteint un consensus ou ont été validées
   - À la demande explicite de l'utilisateur

3. **`termine` → `archive`**:
   - À la demande de l'utilisateur pour archiver une session terminée

### Endpoint de mise à jour du statut

```http
PATCH /api/mcp/sessions/{sessionId}/status
Content-Type: application/json

{
    "status": "en_cours"
}
```

**Réponse:**
```json
{
    "id": "uuid-de-la-session",
    "title": "Titre de la session",
    "status": "en_cours",
    "updated_at": "2026-01-11T10:30:00+00:00"
}
```

## Endpoints MCP disponibles

### Sessions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/sessions/{sessionId}/status` | Obtenir le statut et les statistiques de la session |
| `PATCH` | `/sessions/{sessionId}/status` | **Mettre à jour le statut de la session** |
| `GET` | `/sessions/{sessionId}/annotations` | Lister les annotations de la session |

### Documents

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/sessions/{sessionId}/documents` | Lister les documents |
| `POST` | `/sessions/{sessionId}/documents` | Créer un document |
| `GET` | `/documents/{documentId}` | Lire un document |
| `PUT/PATCH` | `/documents/{documentId}` | Mettre à jour un document |
| `DELETE` | `/documents/{documentId}` | Supprimer un document |

### Annotations

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/documents/{documentId}/annotations` | Lister les annotations d'un document |
| `POST` | `/annotations/{annotationId}/respond` | Répondre à une annotation |
| `POST` | `/annotations/{annotationId}/acknowledge` | Marquer une annotation comme prise en compte |
| `DELETE` | `/annotations/{annotationId}` | Supprimer une annotation obsolète |

### Estimations (Planning Poker)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/sessions/{sessionId}/estimations` | Lister les estimations de la session |
| `POST` | `/sessions/{sessionId}/estimations` | **Créer une estimation** |
| `GET` | `/estimations/{estimationId}` | Obtenir une estimation |
| `POST` | `/estimations/{estimationId}/vote` | Voter sur une estimation |
| `POST` | `/estimations/{estimationId}/reveal` | Révéler les votes |

#### Créer une estimation

```http
POST /api/mcp/sessions/{sessionId}/estimations
Content-Type: application/json
X-Agent-Id: {participant_id}

{
    "title": "Story: Authentification OAuth",
    "description": "Implémenter l'authentification OAuth2",
    "document_id": "uuid-du-document"
}
```

| Paramètre | Type | Requis | Description |
|-----------|------|--------|-------------|
| `title` | string | Oui | Titre de l'estimation |
| `description` | string | Non | Description détaillée |
| `document_id` | UUID | Non | Lier à un document existant |

#### Voter sur une estimation

```http
POST /api/mcp/estimations/{estimationId}/vote
Content-Type: application/json
X-Agent-Id: {participant_id}

{
    "value": "5"
}
```

**Valeurs Fibonacci valides:** `0`, `1`, `2`, `3`, `5`, `8`, `13`, `21`, `?`

> Note: `?` indique une incertitude sur l'estimation.

#### Révéler les votes

```http
POST /api/mcp/estimations/{estimationId}/reveal
```

Une fois révélés, les votes ne peuvent plus être modifiés. La moyenne est calculée automatiquement (exclut les `?`).

## Workflow recommandé pour l'agent

1. **Rejoindre la session** via `/api/sessions/join/{inviteCode}` ou `/api/sessions/agent/create`

2. **Mettre immédiatement le statut à `en_cours`**:
   ```http
   PATCH /api/mcp/sessions/{sessionId}/status
   {"status": "en_cours"}
   ```

3. **Récupérer le statut de la session** pour voir les statistiques:
   ```http
   GET /api/mcp/sessions/{sessionId}/status
   ```

4. **Traiter les annotations prioritaires** retournées dans le statut

5. **Mettre le statut à `termine`** quand tout est traité:
   ```http
   PATCH /api/mcp/sessions/{sessionId}/status
   {"status": "termine"}
   ```

## Headers requis

Pour identifier l'agent comme auteur des modifications:

```http
X-Agent-Id: {participant_id}
```

Le `participant_id` est obtenu lors de la création/jonction à la session.

## Exemple complet

```bash
# 1. Créer une session et rejoindre en tant qu'agent
curl -X POST https://localhost/api/sessions/agent/create \
  -H "Content-Type: application/json" \
  -d '{"title": "Ma session", "agent_name": "Claude"}'

# Réponse: {"session": {"id": "abc-123", ...}, "agent": {"participant_id": "xyz-789", ...}}

# 2. Mettre le statut à "en_cours" immédiatement
curl -X PATCH https://localhost/api/mcp/sessions/abc-123/status \
  -H "Content-Type: application/json" \
  -d '{"status": "en_cours"}'

# 3. Travailler sur la session (créer documents, répondre aux annotations...)

# 4. Quand terminé, mettre le statut à "termine"
curl -X PATCH https://localhost/api/mcp/sessions/abc-123/status \
  -H "Content-Type: application/json" \
  -d '{"status": "termine"}'
```
