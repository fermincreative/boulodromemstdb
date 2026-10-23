# Documentation — Ledenadmin Boulodrôme Maastricht

Technical documentation for developers working on this application. The application itself (and everything users see) is in Dutch; code and these documents are in English.

| Document | What it covers |
|---|---|
| [data-model.md](data-model.md) | Every table: what a row means, its columns and relations, and the rules that live in code rather than in the schema. |

Project-wide conventions, decisions and the feature status are in [`CLAUDE.md`](../CLAUDE.md) in the repository root.

## Keeping this up to date

- A new migration that adds or changes a table or column also updates [data-model.md](data-model.md) in the same change.
- Describe what the data **means** and which rule protects it (with the class that enforces it), not just the column type: the schema already shows types.
- Never put real member data in these documents; use made-up examples ("Jan Jansen", `jan@example.nl`).
