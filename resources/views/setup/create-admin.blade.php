<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiết lập ban đầu - Tạo tài khoản Admin</title>
    {{-- Một chút style để giao diện đẹp hơn --}}
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f9; margin: 0; }
        .setup-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: #333; margin-top: 0; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #555; font-weight: 500; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; transition: border-color 0.2s; }
        input:focus { border-color: #3498db; outline: none; }
        .input-error { border-color: #e74c3c; }
        .error { color: #e74c3c; font-size: 0.875rem; margin-top: 0.25rem; }
        button { width: 100%; padding: 0.75rem; background-color: #3498db; color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; transition: background-color 0.2s; }
        button:hover { background-color: #2980b9; }
    </style>
</head>
<body>
    <div class="setup-card">
        <h1>Tạo tài khoản Admin đầu tiên</h1>
        <form method="POST" action="/setup/create-admin">
            {{ csrf_field() }}

            <div class="form-group">
                <label for="name">Tên của bạn</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="{{ $errors->has('name') ? 'input-error' : '' }}">
                @if($errors->has('name'))
                    <p class="error">{{ $errors->first('name') }}</p>
                @endif
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="{{ $errors->has('email') ? 'input-error' : '' }}">
                @if($errors->has('email'))
                    <p class="error">{{ $errors->first('email') }}</p>
                @endif
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input id="password" type="password" name="password" required class="{{ $errors->has('password') ? 'input-error' : '' }}">
                @if($errors->has('password'))
                    <p class="error">{{ $errors->first('password') }}</p>
                @endif
            </div>

            <button type="submit">Tạo tài khoản</button>
        </form>
    </div>
</body>
</html>
