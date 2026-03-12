# Development Guide

## 🛠️ Development Tools & Quality Checks

This project includes tools to help maintain code quality:

- **Pint:** For PHP code style formatting.
  - Check: `composer lint`
  - Fix: `composer fix`
- **Rector:** For automated PHP code refactoring (configuration in `rector.php`).
- **PHPStan:** For static analysis.
  - Run: `composer analyse`
- **Prettier:** For Blade template formatting (run via pre-commit hook or manually).
- **Pest:** For running tests.
  - Run: `composer test`
  - Run with coverage: `composer test-coverage`

Consider setting up the Git pre-commit hook (script available in the project history if needed) to automate formatting and checks before committing.

## 🔧 Pre-commit Hook Setup

This project uses a Git pre-commit hook to automatically format and check code before it is committed. The hook ensures consistency and helps catch potential issues early.

**Setup (Recommended):**

Git hooks are not version controlled, so you need to set this up manually once per local clone. Follow these steps from the project root:

1. **Create the hook file (if it doesn't exist):**

   ```bash
   touch .git/hooks/pre-commit
   ```

2. **Open the file** (`.git/hooks/pre-commit`) in your text editor.

3. **Paste the entire script content below** into the file, replacing any existing content:

   ```sh
   #!/bin/sh
   #
   # Pre-commit hook that runs formatters (Pint, Rector, Prettier)
   # on staged files and checks for debug statements.
   # Modifications are automatically staged.
   #

   echo "Running Pint on staged PHP files..."

   # Get staged PHP files
   STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

   if [ -z "$STAGED_PHP_FILES" ]; then
     echo "No staged PHP files found to Pint."
   else
     # Check if Pint is installed
     PINT_PATH="./vendor/bin/pint"
     if [ ! -f "$PINT_PATH" ]; then
         echo >&2 "Error: Laravel Pint not found at $PINT_PATH. Please run 'composer install'."
         exit 1
     fi

     # Run Pint on the staged files. Use --quiet to reduce noise, but capture output on error.
     PINT_OUTPUT=$("$PINT_PATH" $STAGED_PHP_FILES 2>&1)
     PINT_EXIT_CODE=$?

     # Check Pint exit code
     if [ $PINT_EXIT_CODE -ne 0 ]; then
       echo >&2 "Pint failed to format PHP files:"
       echo >&2 "$PINT_OUTPUT"
       exit 1
     fi

     # Re-stage the files potentially modified by Pint
     echo "Staging potentially modified PHP files..."
     echo "$STAGED_PHP_FILES" | while IFS= read -r file; do
       # Check if the file still exists (it might have been deleted and staged)
       if [ -f "$file" ]; then
           git add "$file"
       fi
     done
     echo "Pint formatting applied and staged for PHP files."
   fi

   echo "Running Rector on staged PHP files..."

   # We need the list of staged PHP files again, or reuse if available
   # If Pint didn't run, STAGED_PHP_FILES would be empty, re-fetch it.
   if [ -z "$STAGED_PHP_FILES" ] && [ -z "$(git diff --cached --name-only --diff-filter=ACM -- '*.php')" ]; then
      STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')
   fi

   if [ -z "$STAGED_PHP_FILES" ]; then
     echo "No staged PHP files found to Rector."
   else
     # Check if Rector is installed
     RECTOR_PATH="./vendor/bin/rector"
     if [ ! -f "$RECTOR_PATH" ]; then
         echo >&2 "Error: Rector not found at $RECTOR_PATH. Please run 'composer install'."
         exit 1
     fi

     # Run Rector process on the staged files.
     RECTOR_OUTPUT=$("$RECTOR_PATH" process $STAGED_PHP_FILES --no-progress-bar --no-diffs 2>&1)
     RECTOR_EXIT_CODE=$?

     # Check Rector exit code
     # Allow non-zero exit if it just made changes.
     if [ $RECTOR_EXIT_CODE -ne 0 ]; then
         if ! echo "$RECTOR_OUTPUT" | grep -q "Rector is done!"; then
           echo >&2 "Rector failed to process PHP files:"
           echo >&2 "$RECTOR_OUTPUT"
           exit 1
         fi
         echo "Rector applied changes."
     fi

     # Re-stage the files potentially modified by Rector
     echo "Staging potentially modified PHP files after Rector..."
     echo "$STAGED_PHP_FILES" | while IFS= read -r file; do
       # Check if the file still exists
       if [ -f "$file" ]; then
           git add "$file"
       fi
     done
     echo "Rector changes applied and staged for PHP files."
   fi

   echo "Checking Blade formatting with Prettier..."

   # Get staged Blade files
   STAGED_BLADE_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.blade.php')

   if [ -z "$STAGED_BLADE_FILES" ]; then
     echo "No staged Blade files found to check."
   else
     # Check if node_modules exists (basic check for npm install)
     if [ ! -d "node_modules" ]; then
       echo >&2 "Error: node_modules directory not found. Please run 'npm install'."
       exit 1
     fi

     # Run Prettier --write on staged Blade files
     echo "Running Prettier --write on staged Blade files..."
     PRETTIER_OUTPUT=$(npx prettier --write $STAGED_BLADE_FILES 2>&1)
     PRETTIER_EXIT_CODE=$?

     if [ $PRETTIER_EXIT_CODE -ne 0 ]; then
       echo >&2 "Prettier formatting failed for Blade files:"
       echo >&2 "$PRETTIER_OUTPUT"
       exit 1
     fi

     # Add logic to stage modified Blade files
     echo "Staging potentially modified Blade files..."
     echo "$STAGED_BLADE_FILES" | while IFS= read -r file; do
       # Check if the file still exists
       if [ -f "$file" ]; then
           git add "$file"
       fi
     done
     echo "Prettier formatting applied and staged for Blade files."
   fi

   echo "Checking for leftover debug statements (dd, dump)..."

   # Re-fetch staged PHP files if neither Pint nor Rector block ran
   if [ -z "$STAGED_PHP_FILES" ] && [ -z "$STAGED_BLADE_FILES" ]; then
     STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')
   fi

   if [ -n "$STAGED_PHP_FILES" ]; then
       # Search for dd( or dump( - ignore case, show line number, only match whole words
       FORBIDDEN_PATTERN='\b(dd|dump)\('
       DEBUG_OUTPUT=$(echo "$STAGED_PHP_FILES" | xargs grep -nwEi "$FORBIDDEN_PATTERN")

       if [ -n "$DEBUG_OUTPUT" ]; then
           echo >&2 "Error: Found forbidden debug statements in staged PHP files:"
           echo >&2 "$DEBUG_OUTPUT"
           exit 1
       fi
       echo "No leftover PHP debug statements found."
   else
       echo "No staged PHP files to check for debug statements."
   fi

   echo "Pre-commit checks passed."
   # Exit with 0 to allow the commit
   exit 0
   ```

4. **Save and close** the file.

5. **Make the hook executable:**
   ```bash
   chmod +x .git/hooks/pre-commit
   ```

**Checks Performed:**

When you run `git commit`, the hook will automatically perform the following actions on your _staged_ files:

1. **PHP Formatting (Pint):** Runs `vendor/bin/pint` to format staged PHP files according to the project's coding style.
2. **PHP Refactoring (Rector):** Runs `vendor/bin/rector process` to apply configured automated refactorings to staged PHP files.
3. **Blade Formatting (Prettier):** Runs `npx prettier --write` to format staged `*.blade.php` files.
4. **PHP Debug Statement Check:** Scans staged PHP files for leftover debug functions like `dd()` or `dump()` and prevents the commit if found.

Any modifications made by Pint, Rector, or Prettier will be automatically added to your commit.

## 📋 Coding Standards

### PHP Code Style

This project follows PSR-12 coding standards with Laravel-specific conventions:

- Use 4 spaces for indentation
- Follow Laravel naming conventions
- Use type hints where appropriate
- Write descriptive method and variable names

### Blade Templates

- Use TailwindCSS utility classes only
- Follow consistent component structure
- Use proper Livewire directives
- Maintain clean, readable templates

### Database

- Use descriptive migration names
- Include proper foreign key constraints
- Add indexes for performance
- Use proper data types

### Testing

- Write tests for all new functionality
- Use descriptive test names
- Follow AAA pattern (Arrange, Act, Assert)
- Mock external dependencies

## 🔧 Development Workflow

1. **Create feature branch** from main
2. **Write tests** for new functionality
3. **Implement feature** following coding standards
4. **Run quality checks** (`composer lint`, `composer analyse`)
5. **Commit changes** (pre-commit hook will format code)
6. **Create pull request** with descriptive title and description

## 📚 Additional Resources

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Livewire 3 Documentation](https://livewire.laravel.com/docs)
- [TailwindCSS Documentation](https://tailwindcss.com/docs)
- [Pest Testing Framework](https://pestphp.com/docs)
- [Laravel Pulse Documentation](https://laravel.com/docs/12.x/pulse)
- [Laravel Telescope Documentation](https://laravel.com/docs/12.x/telescope)
