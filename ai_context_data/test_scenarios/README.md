# CCC + Canvas AI Test Scenarios

End-to-end test scenarios for validating AI + Canvas + Context Control Center (CCC) functionality.

## Structure

```
test_scenarios/
├── ccc_context/              # CCC context document exports (YAML)
├── supporting_content/       # Input content for tests (copy decks, bios, etc.)
├── phase_0_ccc_setup/        # CCC interface, routing, boundaries
├── phase_1_creating_pages/   # Page creation from copy decks
├── phase_2_editing_pages/    # Media, refinement, editing
├── phase_3_prelaunch/        # SEO, schema, cross-linking
├── phase_4_new_context/      # Adding context post-launch
├── phase_5_performance_trigger/  # GA agent, performance alerts
└── phase_6_improvements/     # Diagnosis, fixes, compliance, publish
```

## File Conventions

Each test case directory contains:

| File | Purpose |
|------|---------|
| `test.yml` | Machine-readable test definition (ai_agents_test compatible) |
| `scenario.md` | Human-readable scenario documentation |
| `before.png` | Screenshot mockup of initial state |
| `after.png` | Screenshot mockup of expected outcome |
| `turn_N_*.png` | Additional screenshots for multi-turn conversations |

Each phase directory contains:

| File | Purpose |
|------|---------|
| `_group.yml` | Test group metadata for ai_agents_test import |

## ai_agents_test Compatibility

These scenarios are structured for eventual contribution to [drupal/ai_agents_test](https://www.drupal.org/project/ai_agents_test).

The `test.yml` files follow the ai_agents_test YAML schema:
- `label`: Test name
- `description`: What the test validates
- `messages`: Conversation history
- `triggering_instructions`: The prompt that triggers the test
- `ai_agent`: Target agent ID
- `rules`: Tool invocation rules (should_be_run, should_not_be_run)
- `response_rule`: LLM-evaluated response criteria

## Screenshot Mockups

Screenshots should be placed in each test directory:
- `before.png` - Canvas/CCC state before AI action
- `after.png` - Expected state after AI action
- Use consistent dimensions (recommended: 1200x800)
- Annotate key areas if helpful

## Running Tests

Once imported into ai_agents_test:

```bash
# Import a test group
drush ai-agents-test:import phase_1_creating_pages/_group.yml

# Run tests
drush ai-agents-test:run --group="CCC Phase 1: Creating Pages"
```

## Contributing

1. Add new scenarios following the directory structure
2. Include both `test.yml` and `scenario.md`
3. Add screenshot mockups
4. Update `scenarios_checklist.md` if new setup is required

## Related Documents

- `full_scenarios-v3.md` - Master narrative document (source of truth)
- `scenarios_checklist.md` - Setup checklist for all required items
