<!-- Interactive Password Requirements -->
<div class="space-y-4">
  <!-- Password strength indicator -->
  <div class="space-y-2">
    <div class="flex items-center justify-between">
      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
        Password strength
      </span>
      <span id="strength-text" class="text-sm font-medium text-red-500">
        Weak
      </span>
    </div>
    <div
      class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
    >
      <div
        id="strength-bar"
        class="h-full rounded-full transition-all duration-500 ease-out"
        style="width: 0%; background-color: #ef4444"
      ></div>
    </div>
  </div>

  <!-- Requirements list -->
  <div class="space-y-3">
    <!-- Length requirement -->
    <div class="flex items-center space-x-3">
      <div
        id="length-check"
        class="flex h-5 w-5 items-center justify-center rounded-full border-2 transition-all duration-200"
      >
        <svg
          class="hidden h-3 w-3 text-white transition-all duration-200"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clip-rule="evenodd"
          ></path>
        </svg>
      </div>
      <span
        id="length-text"
        class="text-sm text-gray-600 transition-colors duration-200 dark:text-gray-400"
      >
        At least 16 characters
      </span>
    </div>

    <!-- Uppercase requirement -->
    <div class="flex items-center space-x-3">
      <div
        id="uppercase-check"
        class="flex h-5 w-5 items-center justify-center rounded-full border-2 transition-all duration-200"
      >
        <svg
          class="hidden h-3 w-3 text-white transition-all duration-200"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clip-rule="evenodd"
          ></path>
        </svg>
      </div>
      <span
        id="uppercase-text"
        class="text-sm text-gray-600 transition-colors duration-200 dark:text-gray-400"
      >
        One uppercase letter
      </span>
    </div>

    <!-- Lowercase requirement -->
    <div class="flex items-center space-x-3">
      <div
        id="lowercase-check"
        class="flex h-5 w-5 items-center justify-center rounded-full border-2 transition-all duration-200"
      >
        <svg
          class="hidden h-3 w-3 text-white transition-all duration-200"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clip-rule="evenodd"
          ></path>
        </svg>
      </div>
      <span
        id="lowercase-text"
        class="text-sm text-gray-600 transition-colors duration-200 dark:text-gray-400"
      >
        One lowercase letter
      </span>
    </div>

    <!-- Number requirement -->
    <div class="flex items-center space-x-3">
      <div
        id="number-check"
        class="flex h-5 w-5 items-center justify-center rounded-full border-2 transition-all duration-200"
      >
        <svg
          class="hidden h-3 w-3 text-white transition-all duration-200"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clip-rule="evenodd"
          ></path>
        </svg>
      </div>
      <span
        id="number-text"
        class="text-sm text-gray-600 transition-colors duration-200 dark:text-gray-400"
      >
        One number
      </span>
    </div>

    <!-- Special character requirement -->
    <div class="flex items-center space-x-3">
      <div
        id="special-check"
        class="flex h-5 w-5 items-center justify-center rounded-full border-2 transition-all duration-200"
      >
        <svg
          class="hidden h-3 w-3 text-white transition-all duration-200"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clip-rule="evenodd"
          ></path>
        </svg>
      </div>
      <span
        id="special-text"
        class="text-sm text-gray-600 transition-colors duration-200 dark:text-gray-400"
      >
        One special character
      </span>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const lengthCheck = document.getElementById('length-check');
    const uppercaseCheck = document.getElementById('uppercase-check');
    const lowercaseCheck = document.getElementById('lowercase-check');
    const numberCheck = document.getElementById('number-check');
    const specialCheck = document.getElementById('special-check');

    const lengthText = document.getElementById('length-text');
    const uppercaseText = document.getElementById('uppercase-text');
    const lowercaseText = document.getElementById('lowercase-text');
    const numberText = document.getElementById('number-text');
    const specialText = document.getElementById('special-text');

    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');

    function updatePasswordRequirements(password) {
      // Length check (16+ characters)
      const hasLength = password.length >= 16;
      updateCheck(lengthCheck, lengthText, hasLength);

      // Uppercase check
      const hasUppercase = /[A-Z]/.test(password);
      updateCheck(uppercaseCheck, uppercaseText, hasUppercase);

      // Lowercase check
      const hasLowercase = /[a-z]/.test(password);
      updateCheck(lowercaseCheck, lowercaseText, hasLowercase);

      // Number check
      const hasNumber = /\d/.test(password);
      updateCheck(numberCheck, numberText, hasNumber);

      // Special character check
      const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
      updateCheck(specialCheck, specialText, hasSpecial);

      // Calculate password strength
      updatePasswordStrength(password);
    }

    function updateCheck(checkElement, textElement, isValid) {
      const svg = checkElement.querySelector('svg');

      if (isValid) {
        // Add success styling
        checkElement.classList.remove(
          'border-gray-300',
          'dark:border-gray-600'
        );
        checkElement.classList.add(
          'border-green-500',
          'bg-green-500',
          'scale-110'
        );
        textElement.classList.remove('text-gray-600', 'dark:text-gray-400');
        textElement.classList.add(
          'text-green-600',
          'dark:text-green-400',
          'font-medium'
        );
        svg.classList.remove('hidden');

        // Add a subtle animation
        checkElement.style.transform = 'scale(1.1)';
        setTimeout(() => {
          checkElement.style.transform = 'scale(1)';
        }, 150);
      } else {
        // Remove success styling
        checkElement.classList.remove(
          'border-green-500',
          'bg-green-500',
          'scale-110'
        );
        checkElement.classList.add('border-gray-300', 'dark:border-gray-600');
        textElement.classList.remove(
          'text-green-600',
          'dark:text-green-400',
          'font-medium'
        );
        textElement.classList.add('text-gray-600', 'dark:text-gray-400');
        svg.classList.add('hidden');
        checkElement.style.transform = 'scale(1)';
      }
    }

    function updatePasswordStrength(password) {
      let score = 0;
      let strength = 'Weak';
      let strengthClass = 'text-red-500';
      let barColor = '#ef4444'; // Red

      // Simple scoring: 1 point per requirement met
      if (password.length >= 16) score += 1;
      if (/[A-Z]/.test(password)) score += 1;
      if (/[a-z]/.test(password)) score += 1;
      if (/\d/.test(password)) score += 1;
      if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) score += 1;

      // Simple strength levels with matching colors
      if (score >= 5) {
        strength = 'Strong';
        strengthClass = 'text-green-500';
        barColor = '#22c55e'; // Green
      } else if (score >= 3) {
        strength = 'Good';
        strengthClass = 'text-yellow-500';
        barColor = '#eab308'; // Yellow
      } else {
        strength = 'Weak';
        strengthClass = 'text-red-500';
        barColor = '#ef4444'; // Red
      }

      // Update strength indicator
      const percentage = (score / 5) * 100;
      strengthBar.style.width = percentage + '%';
      strengthBar.style.backgroundColor = barColor;
      strengthText.textContent = strength;
      strengthText.className = `text-sm font-medium transition-colors duration-300 ${strengthClass}`;
    }

    // Add event listener to password input
    passwordInput.addEventListener('input', function () {
      updatePasswordRequirements(this.value);
    });

    // Initial check
    updatePasswordRequirements(passwordInput.value);
  });
</script>
