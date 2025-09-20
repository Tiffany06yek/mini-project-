// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // Make functions global so onclick can access them
    window.showSignIn = function showSignIn() {
        console.log('Switching to Sign In'); // Debug log
        
        // Update toggle buttons
        document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector('.toggle-btn:first-child').classList.add('active');
        
        // Switch forms
        document.getElementById('signinform').classList.remove('hidden');
        document.getElementById('signupform').classList.add('hidden');
        
        // Update left panel content
        const authLeft = document.getElementById('authLeft');
        const welcomeTitle = document.getElementById('welcomeTitle');
        const welcomeSubtitle = document.getElementById('welcomeSubtitle');
        
        authLeft.classList.remove('signup-mode');
        welcomeTitle.textContent = 'Welcome Back!';
        welcomeSubtitle.textContent = 'We\'ve missed you! Sign in to continue your food journey with us.';
    };

    window.showSignUp = function showSignUp() {
        console.log('Switching to Sign Up'); // Debug log
        
        // Update toggle buttons
        document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector('.toggle-btn:last-child').classList.add('active');
        
        // Switch forms
        document.getElementById('signupform').classList.remove('hidden');
        document.getElementById('signinform').classList.add('hidden');
        
        // Update left panel content
        const authLeft = document.getElementById('authLeft');
        const welcomeTitle = document.getElementById('welcomeTitle');
        const welcomeSubtitle = document.getElementById('welcomeSubtitle');
        
        authLeft.classList.add('signup-mode');
        welcomeTitle.textContent = 'Join XIApee!'; // Updated to match your brand
        welcomeSubtitle.textContent = 'Create your account and discover amazing local restaurants and fresh ingredients delivered to your door.';
    };

    window.togglePassword = function(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleBtn = passwordInput.nextElementSibling;
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.textContent = '🙈';
        } else {
            passwordInput.type = 'password';
            toggleBtn.textContent = '👁️';
        }
    };


    // Sign In Form Handler -- PHP
    const form = document.getElementById('signinform');
    if (!form) return;
  
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); 
  
      if (!form.checkValidity()) { 
        form.reportValidity(); 
        return; }
  
      const btn = form.querySelector('.auth-btn');
      const oldText = btn?.textContent || '';
      if (btn) { 
        btn.disabled = true; 
        btn.textContent = 'Signing In...'; 
        btn.style.opacity = '0.7'; 
        
    }
      
          try {
            const res = await fetch('../backend/signin.php', {
              method: 'POST',
              body: new FormData(form),
              headers: { 'X-Requested-With': 'fetch' } // 让后端识别是AJAX
            });
            const data = await res.json().catch(() => ({}));
      
            if (data.ok === true) {
                window.location.assign('./mainpage.php');
            } else {
              alert(data.msg || 'Login failed.');
            }

          } catch (err) {
            alert('Network Error.');
          } finally {
            // ✅ 延迟恢复按钮（比如 2 秒）
            setTimeout(() => {
              btn.disabled = false;
              btn.textContent = oldText;
              btn.style.opacity = '';
            }, 1500); // 想要多久就改这里（毫秒）
          }
        });
      
    // Sign Up Form Handler
    const signUpForm = document.getElementById('signupform');
    if (signUpForm) {
        signUpForm.addEventListener('submit', function(e) {

            const email = document.getElementById('signUpEmail').value.trim().toLowerCase();
            const campus_email = /@(?:xmu\.edu\.my)$/.test(email);
            const password = document.getElementById('signUpPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const agreeTerms = document.getElementById('agreeTerms').checked;
        
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }

            if (!campus_email) { 
                e.preventDefault();
                alert('Please use your campus email.'); 
                return; 
            }
            
            if (!agreeTerms) {
                e.preventDefault();
                alert('Please agree to the Terms & Conditions');
                return;
            }

            const btn = this.querySelector('.auth-btn');
            if (btn) {
                btn.disabled = true;
                btn.dataset.origText = btn.textContent;
                btn.textContent = 'Creating Account...';
                btn.style.opacity = '0.7';
                btn.style.pointerEvents = 'none';
            }
        });
    }

    const read = new URLSearchParams(location.search);//可以用 p.get('registered')、p.get('err') 取值。
    const box = document.getElementById('msg');
    let text = '';
    let isError = false;

    if (read.get('registered') === '1'){
        text = "Registration seccessful! You can sign in now.";
        if (window.showSignIn) 
            showSignIn();
    }

    const err = read.get('err');
    if (err) {
        const map = {
            empty: 'Please fill up all the fields.',
            invalid_email: 'Invalid email.',
            campus_email: 'Please use your campus email.',
            short_pwd: 'Password must be at least 8 characters.',
            mismatch:  "Password doesn't match confirm password.",
            dup_email: 'Email already registered.',
            server:    'Server Error. Please Try Again.'
        };

        text = map[err] || 'Something went wrong.';
        isError = true;
    }

    if (text) {
        box.textContent = text;
        box.style.display = 'block';
        box.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
        // 清理地址栏参数
        history.replaceState(null, '', location.pathname);
      }


    // Social Login Handlers
    document.querySelectorAll('.social-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const platform = this.title.split(' ')[2];
            alert(`Continue with ${platform} - Feature coming soon!`);
        });
    });

    // Add focus effects to inputs
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.01)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
});
