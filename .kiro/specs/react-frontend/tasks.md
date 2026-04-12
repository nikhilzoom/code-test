# Implementation Plan: react-frontend

## Overview

Build the React 18 + Vite SPA under `services/frontend/`, wire it into the existing Docker Compose stack, and validate correctness with Vitest unit tests and fast-check property-based tests. Each task builds incrementally — scaffolding first, then the API client, auth layer, shared components, pages, routing, and finally Docker/deployment artifacts.

## Tasks

- [ ] 1. Scaffold the Vite + React project
  - Create `services/frontend/` with `package.json` declaring dependencies: `react`, `react-dom`, `react-router-dom`, `axios`, `fast-check`; devDependencies: `vite`, `@vitejs/plugin-react`, `tailwindcss`, `postcss`, `autoprefixer`, `vitest`, `@vitest/coverage-v8`, `jsdom`, `@testing-library/react`, `@testing-library/jest-dom`, `@testing-library/user-event`, `axios-mock-adapter`
  - Create `vite.config.js` with React plugin and Vitest config (`environment: 'jsdom'`, `globals: true`, `setupFiles`)
  - Create `tailwind.config.js` and `postcss.config.js`
  - Create `index.html` (Vite entry point referencing `src/main.jsx`)
  - Create `src/main.jsx` with `ReactDOM.createRoot` mounting `<App />`
  - Create `src/setupTests.js` importing `@testing-library/jest-dom`
  - Create the full directory skeleton: `src/context/`, `src/components/__tests__/`, `src/pages/__tests__/`, `src/context/__tests__/`, `src/__tests__/`
  - _Requirements: 10.1, 10.6_

- [ ] 2. Implement the API client
  - [ ] 2.1 Create `src/api.js` with an Axios instance (`baseURL: 'http://localhost:8000'`, `Content-Type: application/json`)
    - Add request interceptor that reads `jwt` from `localStorage` and sets `Authorization: Bearer <token>`
    - Add response interceptor that handles 401 by clearing `jwt`, `userId`, `accountId` from `localStorage` and redirecting to `/login`
    - Export the instance as default
    - _Requirements: 10.5, 4.2, 2.3_

  - [ ] 2.2 Write unit tests for `api.js`
    - Test that request interceptor attaches Bearer token when `jwt` is present in `localStorage`
    - Test that request interceptor omits Authorization header when no `jwt` is stored
    - Test that response interceptor clears localStorage and redirects on 401
    - Test that non-401 errors are re-rejected
    - _Requirements: 4.2, 10.5_

  - [ ] 2.3 Write property test for API client — Property 19: Global error messages for 500 and network errors
    - `// Feature: react-frontend, Property 19: Global error messages are displayed for 500 and network errors`
    - Validates: Requirements 11.1, 11.2

- [ ] 3. Implement AuthContext
  - [ ] 3.1 Create `src/context/AuthContext.jsx` with `AuthProvider` and `useAuth` hook
    - On mount, read `jwt`, `userId`, `accountId` from `localStorage`; decode JWT expiry; if expired clear keys and set `isAuthenticated: false`; set `isLoading: false` when done
    - `login(token, userId)`: store `jwt` + `userId` in `localStorage`, call `GET /user/{userId}`, then `GET /account` filtered by userId, store resolved `accountId` in `localStorage`, set context state
    - `logout()`: call `POST /user/logout`, then clear `jwt`, `userId`, `accountId` from `localStorage` regardless of response status, set `isAuthenticated: false`
    - `setAccountId(id)`: update context and `localStorage`
    - Expose `{ token, userId, accountId, isLoading, isAuthenticated, login, logout, setAccountId }`
    - _Requirements: 2.3, 3.1, 3.2, 3.4, 4.2, 4.3, 6.1, 6.2, 6.3_

  - [ ] 3.2 Write unit tests for `AuthContext`
    - Test initial load with valid JWT sets `isAuthenticated: true`
    - Test initial load with expired JWT clears localStorage and sets `isAuthenticated: false`
    - Test initial load with no JWT sets `isAuthenticated: false`
    - Test `login()` persists token and resolves accountId
    - Test `logout()` clears session regardless of API response status
    - _Requirements: 3.1, 3.3, 3.4, 4.3_

  - [ ] 3.3 Write property test for AuthContext — Property 5: Auth data persistence round-trip
    - `// Feature: react-frontend, Property 5: Auth data persistence round-trip`
    - Validates: Requirements 2.3, 6.3

  - [ ] 3.4 Write property test for AuthContext — Property 7: Logout sends current JWT as Bearer token
    - `// Feature: react-frontend, Property 7: Logout sends current JWT as Bearer token`
    - Validates: Requirements 4.2

  - [ ] 3.5 Write property test for AuthContext — Property 8: Logout clears session regardless of response status
    - `// Feature: react-frontend, Property 8: Logout clears session regardless of response status`
    - Validates: Requirements 4.3

  - [ ] 3.6 Write property test for AuthContext — Property 11: Account resolution uses the authenticated userId
    - `// Feature: react-frontend, Property 11: Account resolution uses the authenticated userId`
    - Validates: Requirements 6.1

- [ ] 4. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Implement shared components
  - [ ] 5.1 Create `src/components/LoadingSpinner.jsx`
    - Renders a centered animated spinner with no props
    - _Requirements: 3.2, 5.3, 7.7_

  - [ ] 5.2 Create `src/components/ErrorBanner.jsx`
    - Props: `{ message: string | null, onDismiss: () => void }`
    - Renders nothing when `message` is null; otherwise renders the message with a dismiss button that calls `onDismiss`
    - _Requirements: 11.3, 11.4_

  - [ ] 5.3 Write property test for ErrorBanner — Property 20: Error banner is always dismissible
    - `// Feature: react-frontend, Property 20: Error banner is always dismissible`
    - Validates: Requirements 11.4

  - [ ] 5.4 Create `src/components/ProtectedRoute.jsx`
    - Reads `{ isLoading, isAuthenticated }` from `useAuth()`
    - Renders `<LoadingSpinner>` while `isLoading` is true
    - Redirects to `/login` if `!isAuthenticated`
    - Otherwise renders `<Outlet>`
    - _Requirements: 9.1, 9.2, 3.2, 3.3, 3.5_

  - [ ] 5.5 Write unit tests for `ProtectedRoute`
    - Test renders spinner while loading
    - Test redirects to `/login` when unauthenticated
    - Test renders outlet when authenticated
    - _Requirements: 9.1, 9.2_

  - [ ] 5.6 Write property test for ProtectedRoute — Property 6: Valid JWT grants protected route access
    - `// Feature: react-frontend, Property 6: Valid JWT grants protected route access`
    - Validates: Requirements 3.3

  - [ ] 5.7 Create `src/components/Layout.jsx`
    - Renders top nav with links to `/dashboard`, `/transactions`, `/transfer`, and a Logout button
    - Logout button calls `logout()` from `useAuth()`
    - Wraps page content via `<Outlet>`
    - _Requirements: 4.1, 5.6_

  - [ ] 5.8 Create `src/components/BalanceCard.jsx`
    - Props: `{ balance: string, currency: string }`
    - Displays formatted balance and currency
    - _Requirements: 5.2_

  - [ ] 5.9 Write unit tests for `BalanceCard`
    - Test renders balance and currency values
    - _Requirements: 5.2_

  - [ ] 5.10 Write property test for BalanceCard — Property 10: Balance and currency are rendered from API response
    - `// Feature: react-frontend, Property 10: Balance and currency are rendered from API response`
    - Validates: Requirements 5.2

  - [ ] 5.11 Create `src/components/TransactionTable.jsx`
    - Props: `{ entries: LedgerEntry[] }`
    - Renders a table with columns: entry type, amount, currency, date/time (formatted `createdAt`)
    - _Requirements: 7.3_

  - [ ] 5.12 Write unit tests for `TransactionTable`
    - Test renders correct columns for a sample entry
    - Test renders empty state gracefully
    - _Requirements: 7.3_

  - [ ] 5.13 Write property test for TransactionTable — Property 13: Transaction entry renders all required fields
    - `// Feature: react-frontend, Property 13: Transaction entry renders all required fields`
    - Validates: Requirements 7.3

  - [ ] 5.14 Create `src/components/DateRangeFilter.jsx`
    - Props: `{ onFilter: (startDate: string | null, endDate: string | null) => void }`
    - Renders start date and end date inputs; calls `onFilter` on change
    - _Requirements: 7.4, 7.5_

  - [ ] 5.15 Write unit tests for `DateRangeFilter`
    - Test calls `onFilter` with correct values when dates change
    - _Requirements: 7.4, 7.5_

  - [ ] 5.16 Create `src/components/AccountSelector.jsx`
    - Props: `{ users: User[], currentUserId: number, value: number | null, onChange: (accountId: number) => void }`
    - Renders a `<select>` of users excluding the one whose `id` matches `currentUserId`
    - _Requirements: 8.2_

  - [ ] 5.17 Write unit tests for `AccountSelector`
    - Test excludes current user from options
    - Test calls `onChange` with selected value
    - _Requirements: 8.2_

  - [ ] 5.18 Write property test for AccountSelector — Property 15: Current user excluded from transfer destination selector
    - `// Feature: react-frontend, Property 15: Current user excluded from transfer destination selector`
    - Validates: Requirements 8.2

  - [ ] 5.19 Create `src/components/TransferForm.jsx`
    - Props: `{ sourceAccountId: number, onSuccess: (txId: number) => void }`
    - Renders amount input, currency input, `<AccountSelector>`, and submit button
    - Client-side validation: empty/zero/negative/non-numeric amount → "Enter a valid positive amount"; no destination selected → "Select a destination account"
    - On valid submit: call `POST /transaction` via `api.js`; on 201 call `onSuccess(txId)` and reset form; on 400 show API error message; disable submit while in-flight
    - _Requirements: 8.3, 8.4, 8.6, 8.7, 8.8, 8.9, 8.10_

  - [ ] 5.20 Write unit tests for `TransferForm`
    - Test shows "Enter a valid positive amount" for invalid amounts without network call
    - Test shows "Select a destination account" when no destination chosen
    - Test disables submit while in-flight
    - Test calls `onSuccess` with transaction ID on 201
    - Test shows API error message on 400
    - _Requirements: 8.7, 8.8, 8.9, 8.10_

  - [ ] 5.21 Write property test for TransferForm — Property 1: Form submission payload integrity
    - `// Feature: react-frontend, Property 1: Form submission payload integrity`
    - Validates: Requirements 1.2, 2.2, 8.4

  - [ ] 5.22 Write property test for TransferForm — Property 16: Transfer amount validation rejects non-positive values
    - `// Feature: react-frontend, Property 16: Transfer amount validation rejects non-positive values`
    - Validates: Requirements 8.7

  - [ ] 5.23 Write property test for TransferForm — Property 4: API error message passthrough
    - `// Feature: react-frontend, Property 4: API error message passthrough`
    - Validates: Requirements 1.5, 8.9

  - [ ] 5.24 Write property test for TransferForm — Property 17: Success message contains the transaction ID
    - `// Feature: react-frontend, Property 17: Success message contains the transaction ID`
    - Validates: Requirements 8.5

- [ ] 6. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Implement pages
  - [ ] 7.1 Create `src/pages/LoginPage.jsx`
    - Renders email and password fields with a submit button
    - Client-side validation: invalid email format → "Valid email is required"; empty password → "Password is required"; no network request sent on validation failure
    - On valid submit: call `POST /user/login` via `api.js`; on 200 call `login(token, userId)` from `useAuth()` then navigate to `/dashboard`; on 401 show "Invalid email or password."; disable submit while in-flight
    - If already authenticated, redirect to `/dashboard`
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9_

  - [ ] 7.2 Write unit tests for `LoginPage`
    - Test renders email and password fields
    - Test shows "Valid email is required" for invalid email without network call
    - Test shows "Password is required" for empty password without network call
    - Test disables submit while in-flight
    - Test calls `login()` and navigates to `/dashboard` on 200
    - Test shows "Invalid email or password." on 401
    - Test redirects authenticated user to `/dashboard`
    - _Requirements: 2.1, 2.5, 2.6, 2.7, 2.8, 2.9_

  - [ ] 7.3 Write property test for LoginPage — Property 2: Client-side email validation rejects non-email strings
    - `// Feature: react-frontend, Property 2: Client-side email validation rejects non-email strings`
    - Validates: Requirements 1.8, 2.7

  - [ ] 7.4 Create `src/pages/RegisterPage.jsx`
    - Renders name, email, and password fields with a submit button
    - Client-side validation: empty name → "Name is required"; invalid email → "Valid email is required"; password < 6 chars → "Password must be at least 6 characters"; no network request on failure
    - On valid submit: call `POST /user/register`; on 201 auto-submit `POST /user/login` then redirect to `/dashboard`; on 409 show "Email already registered."; on 400 show API error message; disable submit while in-flight
    - If already authenticated, redirect to `/dashboard`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 2.9_

  - [ ] 7.5 Write unit tests for `RegisterPage`
    - Test renders name, email, password fields
    - Test shows "Name is required" for empty name
    - Test shows "Valid email is required" for invalid email
    - Test shows "Password must be at least 6 characters" for short password
    - Test disables submit while in-flight
    - Test auto-logs in and redirects on 201
    - Test shows "Email already registered." on 409
    - Test shows API error on 400
    - _Requirements: 1.1, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9_

  - [ ] 7.6 Write property test for RegisterPage — Property 3: Client-side password length validation
    - `// Feature: react-frontend, Property 3: Client-side password length validation`
    - Validates: Requirements 1.9

  - [ ] 7.7 Create `src/pages/DashboardPage.jsx`
    - On mount: call `GET /account/{accountId}` (accountId from `useAuth()`)
    - On 200: render `<BalanceCard balance currency />`
    - While in-flight: render `<LoadingSpinner>`
    - On 404: show "No account found for your user."
    - On 401: call `logout()` and redirect to `/login`
    - On 500 / network error: show appropriate error via `<ErrorBanner>`
    - Render nav links to `/transactions` and `/transfer`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [ ] 7.8 Write unit tests for `DashboardPage`
    - Test shows spinner while loading
    - Test renders balance and currency on 200
    - Test shows "No account found for your user." on 404
    - Test calls `logout()` and redirects on 401
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

  - [ ] 7.9 Write property test for DashboardPage — Property 9: Dashboard fetches the authenticated user's account
    - `// Feature: react-frontend, Property 9: Dashboard fetches the authenticated user's account`
    - Validates: Requirements 5.1

  - [ ] 7.10 Create `src/pages/TransactionHistoryPage.jsx`
    - On mount: call `GET /ledger/account/{accountId}`; store all entries in state
    - Default display: 10 most recent entries sorted by `createdAt` descending
    - Render `<DateRangeFilter onFilter={...} />`; when both dates set, show all matching entries (no 10-entry cap); filter client-side
    - While in-flight: render `<LoadingSpinner>`
    - Empty array: show "No transactions found."
    - On 401: call `logout()` and redirect to `/login`
    - On 500 / network error: show via `<ErrorBanner>`
    - Render `<TransactionTable entries={displayedEntries} />`
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9_

  - [ ] 7.11 Write unit tests for `TransactionHistoryPage`
    - Test shows spinner while loading
    - Test shows 10 most recent entries by default
    - Test shows "No transactions found." for empty array
    - Test calls `logout()` on 401
    - Test applies date filter client-side
    - _Requirements: 7.2, 7.7, 7.8, 7.9_

  - [ ] 7.12 Write property test for TransactionHistoryPage — Property 12: Transaction list shows 10 most recent entries by default
    - `// Feature: react-frontend, Property 12: Transaction list shows 10 most recent entries by default`
    - Validates: Requirements 7.2

  - [ ] 7.13 Write property test for TransactionHistoryPage — Property 14: Date range filter correctness and cap removal
    - `// Feature: react-frontend, Property 14: Date range filter correctness and cap removal`
    - Validates: Requirements 7.5, 7.6

  - [ ] 7.14 Create `src/pages/TransferPage.jsx`
    - On mount: call `GET /user`; filter out current user; populate `<AccountSelector>`
    - Render `<TransferForm sourceAccountId={accountId} onSuccess={handleSuccess} />`
    - On success: show message containing transaction ID; reset form
    - On 401 from user fetch: call `logout()` and redirect to `/login`
    - On 500 / network error: show via `<ErrorBanner>`
    - _Requirements: 8.1, 8.2, 8.5, 8.6, 8.11_

  - [ ] 7.15 Write unit tests for `TransferPage`
    - Test fetches users on mount and excludes current user from selector
    - Test shows success message with transaction ID on 201
    - Test resets form after success
    - Test calls `logout()` on 401
    - _Requirements: 8.1, 8.2, 8.5, 8.6, 8.11_

- [ ] 8. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Implement App routing
  - Create `src/App.jsx` with `<AuthProvider>` wrapping `<BrowserRouter>`
  - Define routes:
    - `/login` → `<LoginPage>`
    - `/register` → `<RegisterPage>`
    - `<ProtectedRoute>` wrapping `<Layout>` with nested routes:
      - `/dashboard` → `<DashboardPage>`
      - `/transactions` → `<TransactionHistoryPage>`
      - `/transfer` → `<TransferPage>`
    - Index `/` → redirect to `/dashboard`
    - `*` (catch-all) → redirect to `/dashboard` if authenticated, `/login` if not
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ] 9.1 Write unit tests for App routing
    - Test unauthenticated user on protected route redirects to `/login`
    - Test authenticated user on `/login` redirects to `/dashboard`
    - Test catch-all redirects authenticated user to `/dashboard`
    - Test catch-all redirects unauthenticated user to `/login`
    - _Requirements: 9.2, 9.3, 9.4, 9.5_

  - [ ] 9.2 Write property test for App routing — Property 18: Unknown URL redirects based on auth state
    - `// Feature: react-frontend, Property 18: Unknown URL redirects based on auth state`
    - Validates: Requirements 9.4, 9.5

- [ ] 10. Add Docker and Nginx configuration
  - Create `services/frontend/Dockerfile` with multi-stage build:
    - Stage 1 (`build`): `node:20-alpine`, `npm ci`, `npm run build` → outputs `/app/dist`
    - Stage 2 (`serve`): `nginx:alpine`, copy `/app/dist` to `/usr/share/nginx/html`, copy `nginx.conf`
    - `EXPOSE 3000`, `CMD ["nginx", "-g", "daemon off;"]`
  - Create `services/frontend/nginx.conf` with `listen 3000`, `try_files $uri $uri/ /index.html`, and static asset caching headers
  - Update `docker-compose.yml` to add the `frontend` service: build context `.`, dockerfile `services/frontend/Dockerfile`, port `3000:3000`, network `app-network`, `depends_on: nginx`
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.6, 10.7_

- [ ] 11. Update README
  - Add a `Frontend` section documenting: how to start the service (`docker compose up frontend`), the URL (`http://localhost:3000`), the tech stack (React 18, Vite, Tailwind CSS, React Router v6, Axios), and how to run tests (`npm run test` inside `services/frontend/`)
  - _Requirements: 10.3_

- [ ] 12. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Each task references specific requirements for traceability
- Property tests use **fast-check** with `numRuns: 100` minimum; each must include the `// Feature: react-frontend, Property N:` tag
- Unit tests use **Vitest** + **@testing-library/react**; Axios calls are mocked with `axios-mock-adapter` or `vi.mock`
- All 20 correctness properties from the design are covered across tasks 2–9
