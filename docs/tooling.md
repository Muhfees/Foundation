# Tool availability and setup

Checked 2026-11-26 from tools exposed to this session and the current PowerShell PATH. A configured server is not the same as a connected/callable server; no secret-bearing global configuration was dumped. Tool search did not expose callable dedicated connectors for the requested services.

| Capability | Session evidence | Plan / fallback |
|---|---|---|
| Filesystem | Built-in command, patch and image-view tools work inside project | No filesystem MCP needed |
| GitHub | No confirmed callable GitHub MCP; git available, gh not on PATH; no repo in project | Need target repo and authentication for PRs; local docs prepared meanwhile |
| Context7 | No callable tools exposed | Official Drupal/module/Vite docs via web; version checks before code |
| Playwright | Built-in browser/Playwright controls exposed; no dedicated MCP found | Browser inspection plus repo-local Playwright tests in later phases; installation/runtime not yet verified |
| Chrome DevTools | No dedicated callable MCP | Later Lighthouse/trace tooling locally, or connect server |
| Figma | No connector; no design file supplied | Not needed |
| Sentry | No connector | Phase 7+ only; redacted local error logs first |
| Stripe | No connector | Not applicable to chosen Drupal stack |
| Database | No relevant local DB connector | DDEV/Drush database tools, local database only |
| Vercel | No connector | Not applicable to chosen stack |

Python, Git and Node were found. Pillow is not installed; reference sampling uses built-in Windows .NET instead. Docker, PHP, Composer and GitHub CLI were not found on PATH. Verify DDEV and Docker Desktop/WSL2 in Phase 2; do not claim a working Drupal environment yet.

## Optional MCP setup instructions — not executed

Current CLI syntax was checked against [OpenAI MCP documentation](https://developers.openai.com/codex/mcp). The machine's `codex` command was previously not on PATH. Use the bundled executable below while it exists; extension updates may change its versioned location. A standalone Codex installation is another option, not required for the installed skills.

```powershell
$codexExe = 'C:/Users/muhfe/.vscode/extensions/openai.chatgpt-26.1120.50957-win32-x64/bin/windows-x86_64/codex.exe'
& $codexExe mcp list
& $codexExe mcp add context7 -- npx.cmd -y '@upstash/context7-mcp'
& $codexExe mcp add playwright -- npx.cmd -y '@playwright/mcp@latest'
& $codexExe mcp add chrome-devtools -- npx.cmd -y 'chrome-devtools-mcp@latest' --no-usage-statistics --no-performance-crux
& $codexExe mcp add github --url https://api.githubcopilot.com/mcp/ --bearer-token-env-var GITHUB_PAT_TOKEN
```

For GitHub, the named environment variable must be supplied securely to the Codex host; never paste the token into chat, command text or repository files. Prefer a fine-grained token scoped to the chosen repository and only the required permissions, or an available authenticated GitHub plugin. Begin with read access; PR creation needs the corresponding write permissions. [GitHub's Codex setup guide](https://github.com/github/github-mcp-server/blob/main/docs/installation-guides/install-codex.md).

Node/npm and browser prerequisites must be satisfied for the local servers. Package names and flags come from the [Playwright MCP project](https://github.com/microsoft/playwright-mcp) and [Chrome DevTools MCP project](https://github.com/ChromeDevTools/chrome-devtools-mcp). These bootstrap commands follow upstream latest-package instructions; record/review the resolved versions before relying on them for repeatable work.

Restart/reconnect the relevant Codex host, then check `mcp list` and the MCP panel for errors and exposed tools. Do not assume these commands configure every VS Code agent provider. If using the IDE's MCP interface, add the equivalent STDIO command/arguments or Streamable HTTP URL there. For servers that explicitly support OAuth, use `codex mcp login <server-name>`; the GitHub PAT example above uses the environment variable instead.

No Figma, Sentry, database, Stripe or Vercel setup is necessary for Phase 1. Connect those only when the chosen stack and phase require them. Planning continues with the fallbacks above; installing MCP servers is not part of this phase.

## Documentation checked

- [Drupal PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements)
- [Drupal database requirements](https://www.drupal.org/docs/getting-started/system-requirements/database-server-requirements)
- [Paragraphs](https://www.drupal.org/project/paragraphs)
- [Search API](https://www.drupal.org/project/search_api)
- [Source Sans](https://github.com/adobe-fonts/source-sans), [Source Serif](https://github.com/adobe-fonts/source-serif)

Vite implementation docs and exact package release compatibility are deferred until Phase 2, before writing application code. No Alpine dependency is proposed.
