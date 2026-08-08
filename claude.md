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

---

## 하네스: Feedora Full-Stack Feature Dev

**목표:** Feedora 기능을 DB 스키마 → PHP 백엔드 → 바닐라 JS/CSS → 통합 QA 파이프라인으로 end-to-end 구축한다.

**트리거:** 두 개 이상의 레이어를 건드리는 Feedora 기능 구축/추가/수정 요청 시 `feedora-feature-orchestrator` 스킬을 사용하라. 단일 레이어 변경(CSS만, SQL만 등)은 해당 단일 스킬(`feedora-schema-migration` / `feedora-backend-service` / `feedora-frontend-build` / `feedora-integration-qa`)을 직접 사용하거나 직접 응답해도 된다.

**알려진 모순:** 위 "No Third-Party Test Tooling" 규칙은 Playwright를 금지하지만, 레포는 실제로 Playwright(`tests/`, `npm test`)를 포함·사용 중이다. QA가 이 모순을 사용자에게 보고한다.

**변경 이력:**
| 날짜 | 변경 내용 | 대상 | 사유 |
|------|----------|------|------|
| 2026-06-23 | 초기 구성 (4 에이전트 + 4 스킬 + 오케스트레이터) | 전체 | 풀스택 기능 개발 하네스 신규 구축 |
