import { type Page, type Locator } from '@playwright/test';

/** Page object for sign-in.php (form action="sign-in.php"). */
export class SignInPage {
  readonly page: Page;
  readonly form: Locator;
  readonly emailInput: Locator;
  readonly passwordInput: Locator;
  readonly rememberCheckbox: Locator;
  readonly submitButton: Locator;
  readonly errorMessage: Locator;
  readonly successMessage: Locator;

  constructor(page: Page) {
    this.page = page;
    this.form = page.locator('form[action="sign-in.php"]');
    this.emailInput = this.form.locator('#email');
    this.passwordInput = this.form.locator('#password');
    this.rememberCheckbox = this.form.locator('#remember');
    this.submitButton = this.form.getByRole('button', { name: 'Login' });
    this.errorMessage = page.locator('.error-message');
    this.successMessage = page.locator('.success-message');
  }

  async goto() {
    await this.page.goto('/sign-in.php', { waitUntil: 'domcontentloaded' });
  }

  async login(email: string, password: string) {
    await this.emailInput.fill(email);
    await this.passwordInput.fill(password);
    await this.submitButton.click();
  }
}
