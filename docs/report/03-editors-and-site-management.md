# 3. Editors and site management

## 3.1 Three ways to build and manage a site

I compared a **code editor** (Visual Studio Code with Git), a **visual tool** (Adobe Dreamweaver) and a **CMS or static site generator** (WordPress; Jekyll or Hugo).

| | VS Code + Git | Dreamweaver | CMS / static generator |
|---|---|---|---|
| Learning curve | Steepest: HTML, CSS, JavaScript, a server language | Gentler: design view plus code view | CMS easy for authors, harder to customise; generator needs the command line |
| Control | Total | High, but design view's markup needs checking | Through themes and plugins (CMS); full output control (generator) |
| Cost | Free | Paid subscription | Software free; hosting and upkeep are not |
| Non-technical staff | No | Partly | Yes with a CMS; with a generator only if a build runs automatically |
| Deployment | Any server or Git pull | Built-in upload | CMS needs PHP, a database and updates; generator output suits any static host or CDN |

I could not confirm Dreamweaver's current support status or price from Adobe, so those points need checking before submission.

**Choice.** The dashboard needs sign-in, area scoping, an API and live data, so I used a code editor, Laravel and Git from the first day, with commit messages that explain what and why. If ministry staff later need to edit public information pages, a CMS or generator suits those pages only; adding it to the secure application would widen what must be protected.

## 3.2 A note on Microsoft FrontPage

FrontPage was Microsoft's visual web editor of the late 1990s and early 2000s. Microsoft stopped it in 2006 (the date in the brief) and replaced it with the Expression family: Expression Web as the "direct replacement" for professional designers, and Office SharePoint Designer for SharePoint sites [25].

**What it got right.** People who could use a word processor could publish a working site, with templates, themes and one-click publishing. It showed that the barrier to publishing matters as much as the tool's quality.

**Where it failed** (general knowledge; add a source before submission). Visual editing produced heavy, non-standard markup that other browsers and screen readers handled badly. Some features needed FrontPage Server Extensions, a proprietary add-on on the host, which limited where a site could live and was a security concern. Authors were tied to one vendor's workflow.

**Lesson.** An easy tool is worth using only if its output is standard, accessible and portable. That is why this project keeps standard HTML, CSS and JavaScript in Git, and why any future CMS should be judged on the accessibility of what it produces.
