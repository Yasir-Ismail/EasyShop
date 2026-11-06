# EasyShop Learning Guide – Table of Contents

Welcome to the **EasyShop** comprehensive learning guide.  The guide is split into 13 markdown files stored under `documentation/learning-guide/`.  Follow the **recommended order** for a smooth learning curve, but feel free to jump to sections that interest you most.

---

| # | File | Description |
|---|------|-------------|
| 01 | [01-Project-Overview.md](file:///e:/intenship/easyshop/documentation/learning-guide/01-Project-Overview.md) | High‑level introduction, tech stack rationale, system diagram, and overall flow of the application. |
| 02 | [02-Architecture-and-Folder-Structure.md](file:///e:/intenship/easyshop/documentation/learning-guide/02-Architecture-and-Folder-Structure.md) | Detailed directory tree, purpose of each folder/file, and dependency impact analysis (with Mermaid diagram). |
| 03 | [03-Database-Design.md](file:///e:/intenship/easyshop/documentation/learning-guide/03-Database-Design.md) | ER diagram, table definitions, constraints, and discussion of ACID/stock‑restoration logic. |
| 04 | [04-PHP-and-Backend-Code.md](file:///e:/intenship/easyshop/documentation/learning-guide/04-PHP-and-Backend-Code.md) | Core PHP concepts, helper functions, authentication flow, and code excerpts with line numbers. |
| 05 | *(already created earlier – not reproduced here)* | Covers the checkout & order‑management implementation (Phase 6). |
| 06 | [06-Security-Explained.md](file:///e:/intenship/easyshop/documentation/learning-guide/06-Security-Explained.md) | OWASP‑Top‑10 mitigations, code references, limitations, and audit checklist. |
| 07 | [07-Testing-and-Debugging.md](file:///e:/intenship/easyshop/documentation/learning-guide/07-Testing-and-Debugging.md) | Testing pyramid, full test matrix, CI pipeline, and debugging workflow. |
| 08 | [08-Development-Process-and-Challenges.md](file:///e:/intenship/easyshop/documentation/learning-guide/08-Development-Process-and-Challenges.md) | Timeline, major obstacles, solutions, lessons learned, and developer diary. |
| 09 | [09-Deployment-Guide.md](file:///e:/intenship/easyshop/documentation/learning-guide/09-Deployment-Guide.md) | Production preparation, `.htaccess` hardening, environment variables, checklist, troubleshooting, and optional Docker setup. |
| 10 | [10-Hands-on-Practice.md](file:///e:/intenship/easyshop/documentation/learning-guide/10-Hands-on-Practice.md) | Seven practical exercises with hidden solutions to reinforce concepts. |
| 11 | [11-University-Viva-Questions.md](file:///e:/intenship/easyshop/documentation/learning-guide/11-University-Viva-Questions.md) | ≥60 viva‑style questions, short & detailed answers, file references, and follow‑up probes. |
| 12 | [12-Project-Demonstration-Script.md](file:///e:/intenship/easyshop/documentation/learning-guide/12-Project-Demonstration-Script.md) | Step‑by‑step 5‑10 min demo script with slide timings, demo cues, and presenter tips. |
| 13 | **This README** – you are reading it! |

---

### Recommended Study Order
1. **01 → 04** – Build the mental model (overview, architecture, DB, backend). 
2. **06 → 07** – Understand security foundations and how the system is verified. 
3. **08** – Learn the development story, challenges, and decisions. 
4. **09** – Get ready to deploy the app on a live server. 
5. **10** – Do the hands‑on exercises to solidify knowledge. 
6. **11** – Prepare for viva or interview questions. 
7. **12** – Practice the demo script for presentations.

---

### How to Contribute
- Fork the repository, create a branch, edit the markdown files, and open a Pull Request.
- Follow the same markdown style (headings, code fences, Mermaid diagrams) used throughout the guide.
- Keep the **file‑level links** (`file:///…`) as shown so they work inside the Antigravity IDE.

---

*Prepared by the senior core‑PHP developer (AI‑assisted).*
