# Test strategy and tooling

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

How will the MVP be tested, and with which tools?

- Whether to add Vitest for Vue component and composable tests, and its scope (the PRD's §14.2 list).
- Browser end-to-end coverage of the core journeys: Pest 5's browser testing vs another runner, and which journeys qualify.
- How the Catalog and Rating sources are faked in tests (HTTP fakes vs an in-memory fake implementation vs recorded fixtures).
- What must be green in CI before merging.
