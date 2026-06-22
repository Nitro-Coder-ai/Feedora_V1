import { type Page, type Locator } from '@playwright/test';

/** Page object for sign-up.php (form action="sign-up.php"). */
export class SignUpPage {
  readonly page: Page;
  readonly form: Locator;
  readonly usernameInput: Locator;
  readonly emailInput: Locator;
  readonly passwordInput: Locator;
  readonly submitButton: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    this.page = page;
    this.form = page.locator('form[action="sign-up.php"]');
    this.usernameInput = this.form.locator('#username');
    this.emailInput = this.form.locator('#email');
    this.passwordInput = this.form.locator('#password');
    this.submitButton = this.form.getByRole('button', { name: 'Sign Up' });
    this.errorMessage = page.locator('.error-message');
  }

  async goto() {
    await this.page.goto('/sign-up.php', { waitUntil: 'domcontentloaded' });
  }

  async register(username: string, email: string, password: string) {
    await this.usernameInput.fill(username);
    await this.emailInput.fill(email);
    await this.passwordInput.fill(password);
    await this.submitButton.click();
  }
}
