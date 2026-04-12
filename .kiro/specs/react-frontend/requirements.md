# Requirements Document

## Introduction

This feature delivers a React JS single-page application (SPA) that provides a browser-based user interface for the Fund Transfer System. The frontend runs as a Docker service alongside the existing backend microservices and communicates exclusively with the backend APIs exposed at `http://localhost:8000`. It covers four core user-facing capabilities: account registration, login/logout, a dashboard showing the current account balance, a transaction history view with date-range filtering, and a fund transfer form that lets the logged-in user send money to another account.

All pages except Login and Register are protected by JWT-based authentication. The JWT is persisted in `localStorage` so that page refreshes do not cause blank screens or redirect loops. The logged-in user is excluded from the destination account list to prevent self-transfers.

---

## Glossary

- **App**: The React JS single-page application described in this document.
- **API_Client**: The HTTP client layer inside the App responsible for all communication with the backend at `http://localhost:8000`.
- **Auth_Store**: The client-side state manager that holds the authenticated user's JWT, user ID, and account ID.
- **Router**: The client-side routing component (React Router) that controls which page is rendered for a given URL.
- **Protected_Route**: A Router guard component that redirects unauthenticated users to the Login page.
- **Login_Page**: The page that accepts email and password credentials and exchanges them for a JWT.
- **Register_Page**: The page that accepts name, email, and password to create a new user account.
- **Dashboard_Page**: The authenticated page that displays the logged-in user's current account balance.
- **Transaction_History_Page**: The authenticated page that lists ledger entries for the logged-in user's account, with date-range filtering.
- **Transfer_Page**: The authenticated page that allows the logged-in user to initiate a fund transfer to another account.
- **JWT**: JSON Web Token returned by `POST /user/login`, used as a Bearer token in all subsequent authenticated requests.
- **Account**: A financial account record returned by `GET /account/{id}`, containing `id`, `userId`, `balance`, and `currency`.
- **Ledger_Entry**: A record returned by `GET /ledger/account/{accountId}` representing a single debit or credit event on an account.
- **User**: A record returned by `GET /user` or `GET /user/{id}` containing `id`, `name`, and `email`.

---

## Requirements

### Requirement 1: User Registration

**User Story:** As a visitor, I want to register a new account with my name, email, and password, so that I can access the Fund Transfer System.

#### Acceptance Criteria

1. THE Register_Page SHALL render a form containing fields for name, email, and password.
2. WHEN the registration form is submitted with valid name, email, and password values, THE API_Client SHALL send a `POST /user/register` request with the provided values.
3. WHEN the `POST /user/register` response has HTTP status 201, THE App SHALL automatically submit a `POST /user/login` request with the same email and password to obtain a JWT, then redirect the user to the Dashboard_Page.
4. IF the `POST /user/register` response has HTTP status 409, THEN THE Register_Page SHALL display the message "Email already registered."
5. IF the `POST /user/register` response has HTTP status 400, THEN THE Register_Page SHALL display the error message returned by the API.
6. WHILE the registration request is in-flight, THE Register_Page SHALL disable the submit button to prevent duplicate submissions.
7. IF the name field is empty on form submission, THEN THE Register_Page SHALL display a validation message "Name is required" without sending a network request.
8. IF the email field does not match a valid email format on form submission, THEN THE Register_Page SHALL display a validation message "Valid email is required" without sending a network request.
9. IF the password field contains fewer than 6 characters on form submission, THEN THE Register_Page SHALL display a validation message "Password must be at least 6 characters" without sending a network request.

---

### Requirement 2: User Login

**User Story:** As a registered user, I want to log in with my email and password, so that I can access my account and perform transfers.

#### Acceptance Criteria

1. THE Login_Page SHALL render a form containing fields for email and password.
2. WHEN the login form is submitted with a valid email and password, THE API_Client SHALL send a `POST /user/login` request with the provided credentials.
3. WHEN the `POST /user/login` response has HTTP status 200, THE Auth_Store SHALL persist the returned JWT and the authenticated user's ID in `localStorage`.
4. WHEN the JWT is persisted in the Auth_Store, THE Router SHALL redirect the user to the Dashboard_Page.
5. IF the `POST /user/login` response has HTTP status 401, THEN THE Login_Page SHALL display the message "Invalid email or password."
6. WHILE the login request is in-flight, THE Login_Page SHALL disable the submit button to prevent duplicate submissions.
7. IF the email field does not match a valid email format on form submission, THEN THE Login_Page SHALL display a validation message "Valid email is required" without sending a network request.
8. IF the password field is empty on form submission, THEN THE Login_Page SHALL display a validation message "Password is required" without sending a network request.
9. WHEN an authenticated user navigates to the Login_Page or Register_Page, THE Router SHALL redirect the user to the Dashboard_Page.

---

### Requirement 3: Session Persistence on Page Refresh

**User Story:** As an authenticated user, I want the app to remember my session after a page refresh, so that I am not unexpectedly logged out or shown a blank screen.

#### Acceptance Criteria

1. WHEN the App initialises, THE Auth_Store SHALL read the JWT and user ID from `localStorage` before any route is rendered.
2. WHILE the Auth_Store is reading from `localStorage`, THE App SHALL render a loading indicator instead of any page content.
3. WHEN the JWT is present in `localStorage` and has not expired, THE Protected_Route SHALL allow access to authenticated pages without redirecting to the Login_Page.
4. WHEN the JWT is present in `localStorage` but has expired, THE Auth_Store SHALL remove the JWT and user ID from `localStorage` and THE Router SHALL redirect the user to the Login_Page.
5. WHEN the JWT is absent from `localStorage`, THE Protected_Route SHALL redirect the user to the Login_Page.

---

### Requirement 4: User Logout

**User Story:** As an authenticated user, I want to log out, so that my session is securely terminated.

#### Acceptance Criteria

1. THE App SHALL display a logout control on every authenticated page.
2. WHEN the logout control is activated, THE API_Client SHALL send a `POST /user/logout` request with the current JWT in the `Authorization` header.
3. WHEN the `POST /user/logout` response is received (regardless of status code), THE Auth_Store SHALL remove the JWT and user ID from `localStorage`.
4. WHEN the Auth_Store clears the session, THE Router SHALL redirect the user to the Login_Page.

---

### Requirement 5: Dashboard — Account Balance

**User Story:** As an authenticated user, I want to see my current account balance on the dashboard, so that I know how much money I have available.

#### Acceptance Criteria

1. WHEN the Dashboard_Page mounts, THE API_Client SHALL send a `GET /account/{id}` request using the account ID associated with the authenticated user.
2. WHEN the `GET /account/{id}` response has HTTP status 200, THE Dashboard_Page SHALL display the account balance and currency from the response.
3. WHILE the account data request is in-flight, THE Dashboard_Page SHALL display a loading indicator in place of the balance.
4. IF the `GET /account/{id}` response has HTTP status 404, THEN THE Dashboard_Page SHALL display the message "No account found for your user."
5. IF the `GET /account/{id}` response has HTTP status 401, THEN THE Auth_Store SHALL clear the session and THE Router SHALL redirect the user to the Login_Page.
6. THE Dashboard_Page SHALL display navigation links to the Transaction_History_Page and the Transfer_Page.

---

### Requirement 6: Account Resolution After Login

**User Story:** As an authenticated user, I want the app to automatically find my account after login, so that I do not have to manually enter my account ID.

#### Acceptance Criteria

1. WHEN the JWT is stored in the Auth_Store after a successful login, THE API_Client SHALL send a `GET /user/{id}` request to retrieve the authenticated user's profile.
2. WHEN the user profile is retrieved, THE API_Client SHALL send a `GET /account` request (or equivalent) to resolve the account ID associated with the authenticated user's ID.
3. WHEN the account ID is resolved, THE Auth_Store SHALL persist the account ID in `localStorage` alongside the JWT and user ID.
4. IF no account is found for the authenticated user, THEN THE Dashboard_Page SHALL display a prompt allowing the user to create an account via `POST /account`.

---

### Requirement 7: Transaction History

**User Story:** As an authenticated user, I want to view my transaction history, so that I can review past fund movements on my account.

#### Acceptance Criteria

1. WHEN the Transaction_History_Page mounts, THE API_Client SHALL send a `GET /ledger/account/{accountId}` request using the authenticated user's account ID.
2. WHEN the ledger entries are returned, THE Transaction_History_Page SHALL display the 10 most recent entries by default, ordered by `createdAt` descending.
3. THE Transaction_History_Page SHALL display for each entry: the entry type (debit or credit), the amount, the currency, and the date/time of the entry.
4. THE Transaction_History_Page SHALL render a date range filter with a start date field and an end date field.
5. WHEN a start date or end date is applied, THE Transaction_History_Page SHALL filter the displayed entries to only those whose `createdAt` falls within the selected date range.
6. WHEN both a start date and end date are applied, THE Transaction_History_Page SHALL display all entries within the range, not limited to 10.
7. WHILE the ledger request is in-flight, THE Transaction_History_Page SHALL display a loading indicator.
8. IF the `GET /ledger/account/{accountId}` response returns an empty array, THEN THE Transaction_History_Page SHALL display the message "No transactions found."
9. IF the `GET /ledger/account/{accountId}` response has HTTP status 401, THEN THE Auth_Store SHALL clear the session and THE Router SHALL redirect the user to the Login_Page.

---

### Requirement 8: Fund Transfer

**User Story:** As an authenticated user, I want to transfer funds to another account, so that I can send money to other users of the system.

#### Acceptance Criteria

1. WHEN the Transfer_Page mounts, THE API_Client SHALL send a `GET /user` request to retrieve all users.
2. WHEN the user list is returned, THE Transfer_Page SHALL display a selectable list of destination accounts, excluding the account belonging to the currently authenticated user.
3. THE Transfer_Page SHALL render an amount input field and a currency input field alongside the destination account selector.
4. WHEN the transfer form is submitted with a selected destination account, a valid amount, and a currency, THE API_Client SHALL send a `POST /transaction` request with `sourceAccountId`, `destinationAccountId`, `amount`, and `currency`.
5. WHEN the `POST /transaction` response has HTTP status 201, THE Transfer_Page SHALL display a success message containing the transaction ID.
6. WHEN the transfer completes successfully, THE Transfer_Page SHALL reset the form to its initial state.
7. IF the amount field is empty or not a positive number on form submission, THEN THE Transfer_Page SHALL display a validation message "Enter a valid positive amount" without sending a network request.
8. IF no destination account is selected on form submission, THEN THE Transfer_Page SHALL display a validation message "Select a destination account" without sending a network request.
9. IF the `POST /transaction` response has HTTP status 400, THEN THE Transfer_Page SHALL display the error message returned by the API.
10. WHILE the transfer request is in-flight, THE Transfer_Page SHALL disable the submit button to prevent duplicate submissions.
11. IF the `GET /user` response has HTTP status 401, THEN THE Auth_Store SHALL clear the session and THE Router SHALL redirect the user to the Login_Page.

---

### Requirement 9: Route Protection

**User Story:** As a system operator, I want all pages except Login and Register to be accessible only to authenticated users, so that account data is not exposed to unauthenticated visitors.

#### Acceptance Criteria

1. THE Protected_Route SHALL wrap the Dashboard_Page, Transaction_History_Page, and Transfer_Page.
2. WHEN an unauthenticated user navigates to any protected URL, THE Protected_Route SHALL redirect the user to the Login_Page.
3. WHEN an authenticated user navigates to the Login_Page or Register_Page, THE Router SHALL redirect the user to the Dashboard_Page.
4. WHEN an authenticated user navigates to an unknown URL, THE Router SHALL redirect the user to the Dashboard_Page.
5. WHEN an unauthenticated user navigates to an unknown URL, THE Router SHALL redirect the user to the Login_Page.

---

### Requirement 10: Docker Deployment

**User Story:** As a developer, I want the React frontend to run as a Docker service alongside the existing backend services, so that the full system can be started with a single `docker compose up` command.

#### Acceptance Criteria

1. THE App SHALL be packaged in a Dockerfile that produces a production build served by a static file server on port 3000.
2. THE App's Dockerfile SHALL be added to the existing `docker-compose.yml` as a new service named `frontend`.
3. WHEN the `frontend` service starts, THE App SHALL be accessible at `http://localhost:3000`.
4. THE `frontend` service SHALL be connected to the `app-network` Docker network so that it can reach the backend services.
5. THE App SHALL send all API requests to `http://localhost:8000` as the base URL.
6. THE App's Dockerfile SHALL use a multi-stage build: a Node.js build stage to compile the React app, and an Nginx stage to serve the compiled static files.
7. WHEN the App is served by Nginx, THE Nginx configuration SHALL return `index.html` for all routes so that client-side routing works correctly on page refresh.

---

### Requirement 11: Global Error Handling

**User Story:** As an authenticated user, I want the app to handle unexpected API errors gracefully, so that I am never shown a blank screen or an unhandled exception.

#### Acceptance Criteria

1. IF any API_Client request receives an HTTP status 500 response, THEN THE App SHALL display a user-readable error message "Something went wrong. Please try again."
2. IF any API_Client request fails due to a network error (no response received), THEN THE App SHALL display the message "Unable to reach the server. Check your connection."
3. THE App SHALL display error messages in a consistent, visible location on the affected page without navigating away.
4. WHEN an error message is displayed, THE App SHALL provide a way for the user to dismiss it.
