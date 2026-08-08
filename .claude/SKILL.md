# Claude Code Skill: Zero-Downtime SaaS Optimization & Cloudinary Integration

This guide defines the instructions, workflow, and quality gates for **Claude Code** to systematically refactor, modularize, and optimize the **Feedora** social recipe platform without causing any downtime or application failures.

---

## 🧠 Persistent Memory State Tracking
- Claude MUST read and update [.claude/MEMORY.md](file:///c:/Users/moham/Desktop/Feedora_Project_Full/.claude/MEMORY.md) at the beginning and end of every task or phase.
- Use it to keep track of credentials, environment details, modified files, and phase status. This prevents the need to search the codebase repeatedly, significantly reducing token consumption and processing time.

---

## 🛠️ SaaS Stack Reference
- **Backend**: PHP (PDO, OOP repositories/services)
- **Database**: MySQL (MAMP)
- **Frontend**: HTML5, Vanilla CSS3, JavaScript (ES6+)

---

## 🚀 Phase-by-Phase Execution Plan

To prevent regression bugs and ensure zero application downtime, all refactoring must proceed in isolated, testable phases.

### 📁 Phase 1: Directory Restructuring & Modularization
1. **Extract Embedded CSS/JS**:
   - Giant files (e.g., `dashboard.php`, `chefs.php`) contain large `<style>` and `<script>` blocks.
   - Extract these into structured assets:
     - `assets/css/` (e.g., `dashboard.css`, `chefs.css`)
     - `assets/js/` (e.g., `dashboard.js`, `chefs.js`)
2. **OOP Code Splitting**:
   - Extract database logic from page controllers into Repository classes under `includes/` (similar to `includes/StoryRepository.php`).
   - Group page controllers and view files cleanly (e.g., keep routing clean and separate layout templates).
3. **Zero-Downtime Guarantee**:
   - While migrating files, create lightweight redirection wrapper files in the old locations to maintain backward compatibility for bookmarks and active sessions.

### 📷 Phase 2: Client-side Compression & Caching
1. **Client-Side Image Compression**:
   - Implement a JavaScript utility (e.g., in `assets/js/image-compressor.js`) using the HTML5 Canvas API to compress user-selected images before they are uploaded.
   - Limit images to max 1.5MB and compress JPEG/WebP quality to `0.8` client-side.
2. **Local Storage / IndexedDB Caching**:
   - Cache loaded feed images, user avatars, and static assets in the user's browser using `localStorage` or `IndexedDB`.
   - Before downloading images, check if they exist in the local cache, reducing network overhead and making page navigation instantaneous.

### ☁️ Phase 3: Cloudinary CDN Integration
1. **Cloudinary Service Class**:
   - Create `includes/CloudinaryService.php` to handle secure API uploads.
   - Retrieve Cloudinary configurations (defined in `config/config.php`):
     - `CLOUDINARY_CLOUD_NAME` = `'dio1bvxbv'`
     - `CLOUDINARY_API_KEY` = `'692575323714231'`
     - `CLOUDINARY_API_SECRET` = `'W1EIxjVOBGwVGJLuekgCw89AUNI'`
2. **Uploader Middleware**:
   - Intercept media uploads (posts, recipes, stories, avatars, banners) and direct them to Cloudinary.
   - Store the returned Cloudinary secure URLs in the MySQL database.
3. **CDN Image Transformations**:
   - Use Cloudinary dynamic URL parameters to serve auto-formatted (`f_auto`) and auto-quality (`q_auto`) images, dynamically scaled to layout dimensions.

### 🔍 Phase 4: Core Web Vitals & SEO Optimization
1. **Semantic HTML & Metadata**:
   - Maintain a single `<h1>` per page.
   - Auto-generate descriptive meta tags, OpenGraph properties for recipe sharing, and proper schema.org JSON-LD microdata for Google Recipe rich results.
2. **Performance Improvements**:
   - Add `loading="lazy"` to all non-above-the-fold images.
   - Minimize rendering-blocking JavaScript and CSS dependencies.

---

## 🧪 Rigorous Testing & Quality Gates

To ensure no changes disrupt the active SaaS, Claude must apply the following testing constraints **after every single touch (file modification or command execution)**:

1. **PHP Syntax Validation**:
   - Immediately run lint checks on any modified PHP files:
     ```bash
     php -l path/to/file.php
     ```
2. **CSS/JS Integrity Checks**:
   - Ensure console error logs are checked after Javascript changes.
3. **🔌 Claude Extension MCP Environment Testing**:
   - Claude MUST connect directly to the Claude Extension MCP server as defined in [claude.md](file:///c:/Users/moham/Desktop/Feedora_Project_Full/claude.md) and [agent.md](file:///c:/Users/moham/Desktop/Feedora_Project_Full/agent.md).
   - Use the native capabilities of the Claude Extension to:
     * Inspect active page rendering and visual structures at `http://localhost:8000`.
     * Extract browser console log warnings and errors to verify code integrity.
     * Verify hover scaling, active transition layouts, and dynamic elements responsive width adaptation.
   - Do **NOT** install, import, or run external browser automation tools (including Playwright, Puppeteer, Selenium, or Cypress). The Claude Extension MCP is the exclusive testing and environmental validation runtime.
4. **Database Transaction Consistency**:
   - All inserts/updates (especially posts, recipes, and comments) must wrap inside database transactions, verifying that both the main record and related notifications are committed atomically.
5. **Backward Compatibility Testing**:
   - Verify that all existing features (User Authentication, Stories, Chat, Follows) continue to interact perfectly with refactored files.