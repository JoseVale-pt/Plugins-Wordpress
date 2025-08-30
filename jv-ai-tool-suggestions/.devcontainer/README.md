Devcontainer for the jv-ai-tool-suggestions plugin.

What it provides:
- A WordPress site available at http://localhost:8000
- MariaDB database
- A workspace container with PHP 8.1, Composer, WP-CLI, Xdebug and git

How to use:
1. Install Docker Desktop and the VS Code Remote - Containers extension.
2. In VS Code open the plugin folder and use 'Reopen in Container'.
3. Start the compose services from the plugin folder:

   docker compose -f .devcontainer/docker-compose.yml up -d --build

4. The site will be reachable at http://localhost:8000 and the plugin files are mounted into the WP plugins dir.

Notes:
- WP-CLI and Composer are available inside the `workspace` container.
- Xdebug listens on port 9003 and is set to connect back to host.docker.internal. Adjust if necessary.
