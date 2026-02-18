

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

5. Install Canvas dependencies: (Required as we are using the dev release of the module)
```shell
cd web/modules/contrib/canvas
npm install
```

6. Build the Canvas UI:
```shell
cd web/modules/contrib/canvas/ui
npm run build
```

7. Index all contents in the vector database:
```shell
ddev drush sapi-i
```

