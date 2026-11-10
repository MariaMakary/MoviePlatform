# Azure Boards in Claude Code

`.mcp.json` connects Claude Code to our Azure Boards (organization `2023125150197`, project `movieplatform-team`)
through Microsoft's Azure DevOps MCP server. Claude can then read a task by its number ("start 1133"), list the sprint,
and, after asking you, update states, descriptions and comments.

## One-time setup (each teammate)

**1. Install the server** (it starts in under 2 seconds this way; through `npx` it times out):

```powershell
npm install -g @azure-devops/mcp@2.11.0
```

**2. Create a Personal Access Token** in Azure DevOps: user settings (top right) → **Personal access tokens** →
**New Token**.

- Organization: `2023125150197`
- Expiration: e.g. 90 days
- Scopes: **Custom defined** → **Work Items: Read & write**, **Project and Team: Read**

Copy the token (it is shown only once).

**3. Save it as a Windows user environment variable.** Run this one line in PowerShell, with your Azure DevOps email,
and paste the token when asked:

```powershell
$pat = Read-Host "Paste your PAT"; [Environment]::SetEnvironmentVariable("PERSONAL_ACCESS_TOKEN", [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("your.email@ua.edu.lb:$pat")), "User"); Remove-Variable pat
```

Check it (prints `True`, never the value): `[bool][Environment]::GetEnvironmentVariable("PERSONAL_ACCESS_TOKEN", "User")`

**4. Close all VS Code windows and reopen VS Code.** Approve the `azure-devops` MCP server when Claude Code asks.

## Rules

- The token is a password for your Azure DevOps account: never put it in a file in the repo or in a chat.
- When it expires, create a new one and run step 3 again.
- Claude asks before changing anything on the board, since the whole team sees it.
