<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorization Request</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7f9; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
        h1 { font-size: 24px; margin-bottom: 10px; }
        p { margin-bottom: 25px; color: #666; }
        .client-name { font-weight: bold; color: #000; }
        .scopes { list-style: none; padding: 0; margin: 0 0 30px 0; text-align: left; }
        .scopes li { background: #f0f0f0; padding: 10px; border-radius: 4px; margin-bottom: 8px; display: flex; align-items: center; }
        .scopes li::before { content: '✓'; color: #28a745; margin-right: 10px; font-weight: bold; }
        .actions { display: flex; justify-content: space-between; }
        .btn { border: none; padding: 12px 20px; border-radius: 5px; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; width: 48%; }
        .btn-approve { background-color: #28a745; color: white; }
        .btn-deny { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Authorize Application</h1>
        <p>
            The application <strong class="client-name">{{ $client->getName() }}</strong> would like to access your account.
        </p>

        @if (count($scopes) > 0)
            <p>This application will be able to:</p>
            <ul class="scopes">
                @foreach ($scopes as $scope)
                    {{-- Giả sử bạn đã thêm phương thức getDescription() vào ScopeEntity --}}
                    <li>{{ $scope->getDescription() ?? $scope->getIdentifier() }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('oauth.approve') }}">
            @foreach ($request as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach

            <div class="actions">
                <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                <button type="submit" name="action" value="deny" class="btn btn-deny">Deny</button>
            </div>
        </form>
    </div>
</body>
</html>
