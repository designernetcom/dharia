    <!-- template js -->
    <script src="assets/js/script.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>

    <script src="https://www.google.com/recaptcha/api.js?render=6LfasJItAAAAAPw_t1l1xsQYBkKOYozWdzvfP1p4"></script>

    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("myForm");
        const nameInput = document.getElementById("c_name");
        const emailInput = document.getElementById("c_email");
        const phoneInput = document.getElementById("c_phone");
        const messageInput = document.getElementById("c_message");

        const recaptchaInput = document.getElementById("recaptcha_token");

        const messageBox = document.getElementById("check1");
        const submitButton = document.getElementById("btn_submit");
        const buttonText = submitButton.querySelector(".btn-text");

        const RECAPTCHA_SITE_KEY = "6LfasJItAAAAAPw_t1l1xsQYBkKOYozWdzvfP1p4";

        form.addEventListener("submit", function (e) {
          e.preventDefault();

          messageBox.innerHTML = "";
          messageBox.style.color = "";

          /*
        |--------------------------------------------------------------------------
        | Get form values
        |--------------------------------------------------------------------------
        */

          const name = nameInput.value.trim();
          const email = emailInput.value.trim();
          const phone = phoneInput.value.trim();
          const message = messageInput.value.trim();

          /*
        |--------------------------------------------------------------------------
        | Frontend validation
        |--------------------------------------------------------------------------
        */

          const nameRegex = /^[a-zA-Z\s.'-]+$/;
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          const phoneRegex = /^[0-9+\-\s().]{7,20}$/;

          if (name === "") {
            showError("Please enter your name.");
            nameInput.focus();
            return;
          }

          if (!nameRegex.test(name)) {
            showError("Please enter a valid name.");
            nameInput.focus();
            return;
          }

          if (email === "") {
            showError("Please enter your email address.");
            emailInput.focus();
            return;
          }

          if (!emailRegex.test(email)) {
            showError("Please enter a valid email address.");
            emailInput.focus();
            return;
          }

          if (phone === "") {
            showError("Please enter your phone number.");
            phoneInput.focus();
            return;
          }

          if (!phoneRegex.test(phone)) {
            showError("Please enter a valid phone number.");
            phoneInput.focus();
            return;
          }

          if (message === "") {
            showError("Please enter your message.");
            messageInput.focus();
            return;
          }

          /*
        |--------------------------------------------------------------------------
        | Disable button
        |--------------------------------------------------------------------------
        */

          submitButton.disabled = true;
          buttonText.textContent = "Sending...";

          /*
        |--------------------------------------------------------------------------
        | reCAPTCHA v3
        |--------------------------------------------------------------------------
        */

          grecaptcha.ready(function () {
            grecaptcha
              .execute(RECAPTCHA_SITE_KEY, {
                action: "contact_form",
              })
              .then(function (token) {
                /*
                |--------------------------------------------------------------------------
                | IMPORTANT
                | Create FormData manually
                |--------------------------------------------------------------------------
                */

                const formData = new FormData();

                formData.append("name", name);
                formData.append("email", email);
                formData.append("phone", phone);
                formData.append("message", message);
                formData.append("recaptcha_token", token);

                /*
                |--------------------------------------------------------------------------
                | Debug - check what is being sent
                |--------------------------------------------------------------------------
                */

                console.log("Name:", name);
                console.log("Email:", email);
                console.log("Phone:", phone);
                console.log("Message:", message);
                console.log("reCAPTCHA token:", token);

                /*
                |--------------------------------------------------------------------------
                | Send to PHP
                |--------------------------------------------------------------------------
                */

                return fetch("contact_valid.php", {
                  method: "POST",
                  body: formData,
                });
              })
              .then(function (response) {
                return response.text();
              })
              .then(function (responseText) {
                console.log("PHP Response:", responseText);

                let result;

                try {
                  result = JSON.parse(responseText);
                } catch (error) {
                  console.error("Invalid JSON from PHP:", responseText);

                  throw new Error("Invalid response from server.");
                }

                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */

                if (result.success === true) {
                  showSuccess(result.message);

                  form.reset();

                  buttonText.textContent = "Send A Message";
                } else {
                  showError(result.message || "Unable to send your message.");

                  buttonText.textContent = "Try Again";
                }
              })
              .catch(function (error) {
                console.error("FORM ERROR:", error);

                showError("Something went wrong. Please try again.");

                buttonText.textContent = "Try Again";
              })
              .finally(function () {
                submitButton.disabled = false;
              });
          });
        });

        /*
    |--------------------------------------------------------------------------
    | Error
    |--------------------------------------------------------------------------
    */

        function showError(message) {
          messageBox.textContent = message;

          messageBox.style.color = "red";

          messageBox.style.display = "block";
        }

        /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

        function showSuccess(message) {
          messageBox.textContent = message;

          messageBox.style.color = "green";

          messageBox.style.display = "block";
        }
      });
    </script>