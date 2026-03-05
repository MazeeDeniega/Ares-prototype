{{-- <!DOCTYPE html>
<html>
  <head>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
  </head>
  <body>
    <script>
      window.__LARAVEL__ = {
        csrf: "{{ csrf_token() }}",
        loginRoute: "{{ route('login') }}",
        errors: "{{ $errors->first() }}"
      };
    </script>
    <div id="app"></div>
  </body>
</html> --}}

<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body style="padding:20px">
    <h2>Login</h2>
    @if($errors->any())
        <p style="color:red">{{ $errors->first() }}</p>
    @endif
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <input type="text" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="/register">Register</a></p>
    <p><a href="/jobs">View Jobs (Public)</a></p>
</body>
</html>