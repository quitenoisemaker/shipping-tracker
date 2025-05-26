# Contributing to ShippingTracker

Thank you for your interest in contributing to ShippingTracker! This open-source Laravel package simplifies shipment tracking and webhook handling for Africa and Europe logistics. We’re passionate about community-driven development and welcome contributions from developers of all skill levels.

## How to Contribute
We use GitHub for collaboration. Here’s how you can help:

### Reporting Issues
- Check [existing issues](https://github.com/quitenoisemaker/shipping-tracker/issues) to avoid duplicates.
- Open a new issue with:
  - A clear title and description.
  - Steps to reproduce (e.g., code snippet, error message).
  - Environment details (PHP/Laravel version, provider used).
- Example: “Webhook fails for Sendbox with invalid API key error.”

### Submitting Pull Requests
1. **Fork the Repository**:
   ```bash
   git clone https://github.com/your-username/shipping-tracker.git
   cd shipping-tracker
   ```
2. **Create a Branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make Changes**:
   - Follow the coding standards below.
   - Add tests for new features (e.g., new provider like DHL).
   - Update `README.md` or `CHANGELOG.md` if needed.
4. **Test Locally**:
   ```bash
   composer install
   ./vendor/bin/phpunit
   ```
5. **Commit and Push**:
   ```bash
   git commit -m "Add DHL provider support"
   git push origin feature/your-feature-name
   ```
6. **Open a Pull Request**:
   - Go to [github.com/quitenoisemaker/shipping-tracker](https://github.com/quitenoisemaker/shipping-tracker).
   - Submit a PR with a clear description of changes and related issue (if any).

## Coding Standards
- Follow PSR-12 for PHP code.
- Use Laravel conventions (e.g., `StudlyCase` for classes, `snake_case` for database columns).
- Write clear, documented code (e.g., PHPDoc blocks for methods).
- Ensure tests pass: `./vendor/bin/phpunit`.

## Development Setup
1. Clone the repo and install dependencies:
   ```bash
   git clone https://github.com/quitenoisemaker/shipping-tracker.git
   cd shipping-tracker
   composer install
   ```
2. Set up a local Laravel app to test:
   - Link the package via Composer path:
     ```json
     "repositories": [
         {
             "type": "path",
             "url": "/path/to/shipping-tracker"
         }
     ]
     ```
   - Install: `composer require quitenoisemaker/shipping-tracker`.
3. Configure a test database in `phpunit.xml`.
4. Run tests: `./vendor/bin/phpunit`.

## Ideas for Contributions
- Add new providers (e.g., DHL, FedEx).
- Improve webhook performance or error handling.
- Enhance documentation (e.g., more examples).
- Fix bugs or add tests for edge cases.

## Community
Join the discussion on [GitHub Issues](https://github.com/quitenoisemaker/shipping-tracker/issues) or reach out via [samsonojugo@gmail.com](samsonojugo@gmail.com). Your ideas and feedback make this project better!

## Code of Conduct
Be respectful and inclusive. We follow the [Contributor Covenant](https://www.contributor-covenant.org/).

Thank you for helping make ShippingTracker a valuable tool for the Laravel community!