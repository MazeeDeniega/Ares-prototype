<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
</head>
<body>
    <script>
        window.__LARAVEL__ = {
            user: {
                name: "{{ Auth::user()->name }}",
                role: "{{ Auth::user()->role }}"
            },
            users: @json($users),
            csrf: "{{ csrf_token() }}",
            flash: {
                success: "{{ session('success') }}",
                error: "{{ session('error') }}"
            }
        };
    </script>

    <div id="app"></div>
</body>
</html>

{{-- <!DOCTYPE html>
<html>
<head><title>Admin Dashboard</title></head>
<body style="padding:20px">
    <h2>Admin Dashboard</h2>
    <p>Welcome, {{ Auth::user()->name }} (Admin)</p>
    <form action="/logout" method="POST" style="display:inline">@csrf <button type="submit">Logout</button></form>

    <hr>
    @if(session('success'))
        <p style="color:green">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p style="color:red">{{ session('error') }}</p>
    @endif

    <h3>All Users</h3>
    <table border="1" cellpadding="5">
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr>
        @foreach($users as $user)
        <tr>
            <td>{{ $user->id }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>
                <form action="/admin/users/{{ $user->id }}/role" method="POST" style="display:inline">
                    @csrf
                    <select name="role" onchange="this.form.submit()">
                        <option value="applicant" {{ $user->role == 'applicant' ? 'selected' : '' }}>Applicant</option>
                        <option value="recruiter" {{ $user->role == 'recruiter' ? 'selected' : '' }}>Recruiter</option>
                        <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </form>
            </td>
            <td>
                @if($user->role !== 'admin')
                <form action="/admin/users/{{ $user->id }}" method="POST" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete user?')">Delete</button>
                </form>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
</body>
</html> --}}
