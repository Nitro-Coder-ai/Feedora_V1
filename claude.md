# Claude Extension Connection & Direct Environment Guidelines

This document provides system instructions to force **Claude Code** to connect directly with the **Claude Extension MCP** for real-time environment inspection and testing, bypassing third-party testing software.

---

## 🔌 Connection Protocol
1. **Primary Interface**: Establish direct connection with the active Claude Extension MCP server.
2. **Environment Access**: Use the native extension tools to read the active DOM, inspect loaded styles, check current environment variables, and monitor logs.
3. **No Third-Party Test Tooling**: You are prohibited from installing or running Playwright, Puppeteer, Cypress, or Selenium. The Claude Extension MCP is the single source of environment validation.

---

## 👁️ Visual & Environment Inspection
- **Direct Workspace Sync**: Sync all modifications directly with the IDE using the extension.
- **MCP Console Log Extraction**: Extract client-side warning logs and exceptions directly through the extension's browser connection tools.
- **MCP Layout Verification**: Instruct the extension to render and check layout alignments across desktop and mobile form factors, validating page response directly in the workspace.
