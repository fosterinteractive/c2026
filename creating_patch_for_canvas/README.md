The `canvas` folder in the `source` directory includes all the changes required in the Canvas module for the demo to work.
Follow these steps to create a patch.

The patch includes changes for the following Canvas issues:

- https://www.drupal.org/project/canvas/issues/3549232
- https://www.drupal.org/project/canvas/issues/3533079
- https://www.drupal.org/project/canvas/issues/3545816
- https://www.drupal.org/project/canvas/issues/3558241
- https://www.drupal.org/project/canvas/issues/3548718
- https://www.drupal.org/project/canvas/issues/3551315
- https://www.drupal.org/project/canvas/issues/3569120
- https://www.drupal.org/project/canvas/issues/3571988
- https://www.drupal.org/project/canvas/issues/3541873

1. Go to the clone directory:

```bash
cd clone_here
```

2. Clone the Canvas module from its repository:

```bash
git clone git@git.drupal.org:project/canvas.git
```

3. Replace the entire `canvas/module/canvas_ai` folder in the cloned Canvas with the one from the source directory.

4. Replace the file `canvas/ui/src/components/aiExtension/AiWizard.tsx` in the cloned directory with the one from the source directory.

5. Copy the folder `canvas/src/Component` from the source directory and place it in the cloned Canvas folder.

6. Find `canvas.services.yml` in the cloned Canvas module and add the following service definitions:

```yaml
  Drupal\canvas\Component\Schema\PropChoiceOptionsResolver:
    arguments:
      $stringTranslation: '@string_translation'
  Drupal\canvas\Component\Schema\PropMetadataNormalizer: {}
```

7. Generate the patch:

```bash
cd <cloned-canvas-folder>
git add -A
git diff --cached > patchname.patch
```

## Creating patch with the script

1. Clone Canvas into `clone_here`:

```bash
cd clone_here
git clone git@git.drupal.org:project/canvas.git
```

2. Check out the required branch in Canvas (for example, `1.1.0` or `1.x`):

```bash
cd canvas
git checkout 1.1.0
# or
git checkout 1.x
```

3. Make the script executable:

```bash
cd /creating_patch_for_canvas
chmod +x ./generate_canvas_patch.sh
```

4. Run the script:

```bash
./generate_canvas_patch.sh
```
