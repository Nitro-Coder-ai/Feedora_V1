# Agent Execution & Testing Boundary Specification

This document defines the testing boundaries and runtime instructions for AI agents working in this repository.

---

## 🚫 Forbidden Software
- Do **NOT** install, initiate, or execute external web-scraping or testing frameworks (including Playwright, Puppeteer, Selenium, Cypress).
- Do **NOT** configure headless browser runtimes in the terminal.

## 🔌 Mandatory MCP Testing Flow
- **Direct MCP Integration**: The agent must connect to the Claude Extension MCP server already running in the host environment.
- **Client-Side Testing**: Validate visual layout responsiveness, verify interactive states, and check JavaScript console logs exclusively through the native MCP endpoints of the Claude Extension.
- **Phased Validation Checkpoints**: After every single file change ("every touch"), send an update statement to the Claude Extension MCP to ensure the changes are rendered and synced in the environment.
