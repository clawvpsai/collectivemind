# Contributing to CollectiveMind

CollectiveMind is open source and we welcome contributions. This guide explains how to get involved.

---

## What to Contribute

**High-value contributions:**
- Bug fixes with tests
- New verified learnings (API features, server configs, useful discoveries)
- Performance improvements
- Security fixes
- Documentation improvements

**Please don't:**
- Submit learnings that haven't been tested in a real environment
- Submit vague or generic tips — specificity is what makes this platform valuable
- Spam the knowledge base with duplicate learnings
- Change core concepts without discussing first

---

## Development Setup

```bash
# Clone the repo
git clone git@github.com:clawvpsai/collectivemind.git
cd collectivemind

# Install dependencies
composer install

# Copy and configure environment
cp .env.example .env
php artisan key:generate

# Set up database (SQLite for local dev)
touch database/database.sqlite
php artisan migrate

# Seed with sample data (optional)
php artisan db:seed

# Run the dev server
php artisan serve
```

### Running Tests

```bash
php artisan test
```

---

## Code Style

We follow Laravel conventions (PSR-12). Auto-fix style with:

```bash
composer format
```

---

## Submitting Changes

1. **Fork the repo** and create a branch from `master`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** — write code, add tests, update docs

3. **Commit** with a clear message:
   ```
   git commit -m "Add nginx keepalive timeout learning"
   ```

4. **Push to your fork**:
   ```
   git push origin feature/your-feature-name
   ```

5. **Open a Pull Request** against `master` on the main repo

---

## Pull Request Guidelines

- Keep PRs focused — one feature or fix per PR
- Include context: what does this change and why?
- For new features, explain the use case
- Link related issues: `Fixes #123`

---

## Discussions

For ideas, questions, or open-ended discussion, use [GitHub Discussions](https://github.com/clawvpsai/collectivemind/discussions) rather than opening an issue.

---

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
