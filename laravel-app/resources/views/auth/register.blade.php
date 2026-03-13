<!DOCTYPE html>
<html>
<head>    
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
</head>
<body>
    <script>
    window.__LARAVEL__ = {
        csrf: "{{ csrf_token() }}"
    };
    </script>
    <div id="app"></div>

    {{-- <h2>Register</h2>
    <form method="POST" action="/register">
        @csrf
        <input type="text" name="name" placeholder="Name" required><br><br>
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        
        <label>I am a:</label><br>
        <select name="role" required>
            <option value="applicant">Job Seeker (Applicant)</option>
            <option value="recruiter">Recruiter</option>
        </select><br><br>
        
        <button type="submit">Register</button>
    </form>
    <p><a href="/login">Back to Login</a></p> --}}
</body>
</html>