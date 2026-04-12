# Coding Standards

These standards apply to all sessions and all features in this project.

## 1. Design Patterns

- **Repository Pattern**: Use the Repository pattern for all data access logic. Each entity/model must have a corresponding repository interface and implementation. Services must depend on repository interfaces, not concrete implementations.
- **Factory Pattern**: Use the Factory pattern wherever object creation logic is non-trivial or needs to be abstracted. Factories should be used to instantiate domain objects, DTOs, and complex value objects.

## 2. Code Documentation & Annotations

- All classes, methods, and properties must have PHPDoc annotations/comments.
- Annotations must be detailed enough to support automatic API documentation generation (e.g., using tools like `nelmio/api-doc-bundle` or OpenAPI/Swagger annotations).
- Every public API endpoint controller method must include:
  - `@Route` annotation
  - `@param` and `@return` tags
  - A brief description of what the endpoint does
  - Expected request/response formats

## 3. Unit Testing

- Every feature must have corresponding unit tests.
- Unit tests must be written using PHPUnit.
- Tests must cover:
  - Happy path (expected behavior)
  - Edge cases
  - Error/exception scenarios
- Repository and service classes must be tested with mocked dependencies.
- Minimum coverage expectation: all public methods must have at least one test.

## 4. Change Management

- **Existing functionality changes**: If a change is required in existing working functionality, first show the proposed changes to the user for review. Only apply the changes to the respective file(s) after the user has verified and approved them.

- **Sub-feature threshold**: If a required change covers more than 5% of a feature (in terms of files touched, logic altered, or scope), treat it as a sub-feature. Do planning first — define tasks and get approval — then execute the tasks sequentially.

- **README updates**: After any change, check whether the README needs to be updated to reflect the new behaviour, configuration, endpoints, or architecture. If so, update it as part of the same change.
