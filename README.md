

1. Install DDEV following the [documentation](https://ddev.com/get-started/)
2. Open the command line and `cd` to the root directory of this project
3. Set up your environment:
```shell
cp .env.template .ddev/.env
```
Then open `.ddev/.env` and set your OpenAI key in the file.

4. From the root of the project, run:
```shell
ddev demo-setup
```

## Exporting Content

After making changes in Drupal, use these commands to export content back into the recipes:

| Command | What it exports |
|---|---|
| `ddev export-all-content` | All content entities (canvas pages, nodes, media, menu links, taxonomy terms) |
| `ddev export-canvas-pages` | Canvas pages only |
| `ddev export-media` | Media and file entities only |
| `ddev export-ai-context` | AI Context items and usage records |

