import { test as base } from '@playwright/test';
import { SignUpPage } from './pages/SignUpPage';
import { SignInPage } from './pages/SignInPage';
import { DashboardPage } from './pages/DashboardPage';

/** Page-object fixtures so specs read cleanly: `async ({ dashboardPage }) => ...`. */
type Pages = {
  signUpPage: SignUpPage;
  signInPage: SignInPage;
  dashboardPage: DashboardPage;
};

export const test = base.extend<Pages>({
  signUpPage: async ({ page }, use) => { await use(new SignUpPage(page)); },
  signInPage: async ({ page }, use) => { await use(new SignInPage(page)); },
  dashboardPage: async ({ page }, use) => { await use(new DashboardPage(page)); },
});

export { expect } from '@playwright/test';
