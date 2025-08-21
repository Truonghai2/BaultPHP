<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BaultPHP | Application Error</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/BaultPHP-icon.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #8b1a1a;
            --dark-red: #6d1313;
            --light-gray: #2d3748;
            --border-gray: #4a5568;
            --text-dark: #e2e8f0;
            --text-muted: #a0aec0;
            --code-bg: #1a202c;
            --code-text: #cbd5e0;
            --error-bg: rgba(139, 26, 26, 0.2);
            --success-green: #38a169;
            --warning-yellow: #d69e2e;
            --info-blue: #3182ce;
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --card-bg: #2a2a2a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6; 
            color: var(--text-dark); 
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }

        /* Animated background particles */
        .bg-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            opacity: 0.1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .container { 
            max-width: 1400px; 
            margin: 20px auto; 
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            /* border: 1px solid var(--border-gray); */
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        .header { 
            background: linear-gradient(135deg, var(#2b2b2b), var(#2b2b2b));
            color: white; 
            padding: 30px 40px; 
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .header h1 { 
            margin: 0; 
            font-size: 28px; 
            font-weight: 700; 
            word-wrap: break-word;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .error-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .header p { 
            margin: 15px 0 0; 
            opacity: 0.95; 
            word-wrap: break-word;
            font-size: 16px;
            font-weight: 300;
        }

        #copy-error-btn {
            position: absolute;
            top: 30px;
            right: 40px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #copy-error-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .content { 
            padding: 40px; 
        }

        .exception-details { 
            margin-bottom: 40px;
            padding: 25px;
            background: linear-gradient(135deg, var(--error-bg), rgba(220, 53, 69, 0.05));
            border-left: 5px solid var(--primary-red);
            border-radius: 10px;
            position: relative;
        }

        .exception-details::before {
            content: '\f071';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 25px;
            right: 25px;
            font-size: 24px;
            color: var(--primary-red);
            opacity: 0.3;
        }

        .exception-details .file-info { 
            font-size: 18px; 
            color: var(--text-muted); 
            word-wrap: break-word;
        }

        .exception-details .file-info strong { 
            color: var(--primary-red);
            font-weight: 600;
        }

        .section-title { 
            font-size: 24px; 
            margin-bottom: 20px; 
            color: var(--text-dark);
            border-bottom: 3px solid var(--primary-red);
            padding-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-red), transparent);
        }

        .code-snippet-container { 
            margin-bottom: 40px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .code-header {
            background: linear-gradient(90deg, #2d3748, #4a5568);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 500;
        }

        .code-header .filename {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .code-header .line-info {
            background: var(--primary-red);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .code-snippet { 
            background: var(--code-bg);
            color: var(--code-text);
            padding: 0;
            font-family: 'JetBrains Mono', 'Fira Code', 'SFMono-Regular', Consolas, monospace;
            font-size: 14px;
            max-height: 500px;
            overflow-y: auto;
        }

        .code-snippet pre { 
            margin: 0; 
        }

        .code-snippet .line { 
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }

        .code-snippet .line:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .code-snippet .line-number { 
            min-width: 60px; 
            color: #636d83; 
            text-align: right; 
            padding: 0 20px;
            user-select: none;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }

        .code-snippet .line-content { 
            padding: 0 20px;
            white-space: pre-wrap; 
            word-wrap: break-word;
            flex: 1;
        }

        .code-snippet .line.is-error { 
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.4), rgba(220, 53, 69, 0.1));
            border-left: 4px solid var(--primary-red);
            animation: highlight 0.5s ease-in-out;
        }

        @keyframes highlight {
            0% { background-color: rgba(220, 53, 69, 0.6); }
            100% { background-color: rgba(220, 53, 69, 0.4); }
        }

        .stack-trace { 
            background: var(--code-bg);
            color: var(--code-text);
            padding: 25px;
            border-radius: 15px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
        }

        .stack-trace ol { 
            padding-left: 30px; 
            margin: 0; 
            list-style-type: none;
            counter-reset: stack-counter;
        }

        .stack-trace li { 
            padding: 12px 0;
            border-bottom: 1px solid #3e4451;
            position: relative;
            transition: all 0.3s ease;
            counter-increment: stack-counter;
        }

        .stack-trace li::before {
            content: counter(stack-counter);
            position: absolute;
            left: -30px;
            top: 12px;
            background: var(--primary-red);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .stack-trace li:last-child { 
            border-bottom: none; 
        }

        .stack-trace li:hover {
            background-color: rgba(255, 255, 255, 0.05);
            padding-left: 10px;
            border-radius: 8px;
        }

        .stack-trace .file { color: #98c379; }
        .stack-trace .line { color: #61afef; }
        .stack-trace .function { color: #c678dd; }
        .stack-trace .class { color: #e5c07b; }
        .stack-trace .type { color: #d19a66; }

        .details-grid { 
            /* display: grid;  */
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); 
            gap: 25px;
            margin-top: 30px;
        }

        .details-box { 
            background: white;
            border: 1px solid var(--border-gray);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .details-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .details-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-red), var(--info-blue));
        }

        .details-box h3 { 
            font-size: 18px;
            background: linear-gradient(135deg, #1a202c, #2b2b2b);
            padding: 20px 25px;
            margin: 0;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .details-box table { 
            width: 100%; 
            border-collapse: collapse;
        }

        .details-box th, .details-box td { 
            padding: 12px 25px;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
            font-size: 14px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .details-box th {
            width: 35%;
            background: #1a202c;
            font-weight: 600;
            color: var(--text-muted);
            border-right: 3px solid var(--primary-red);
        }

        .details-box td {
            background: #1a202c;
            color: var(--text-dark);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }

        .details-box tr:hover {
            background: rgba(220, 53, 69, 0.02);
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-red);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .container { margin: 10px auto; border-radius: 15px; }
            .header { padding: 25px 20px; }
            .header h1 { font-size: 22px; }
            .content { padding: 25px 20px; }
            .code-snippet { font-size: 12px; }
            .stack-trace { font-size: 11px; padding: 15px; }
            .details-grid { grid-template-columns: 1fr; gap: 15px; }
            .details-box th, .details-box td { 
                font-size: 12px; 
                padding: 8px 15px; 
            }
            .section-title { font-size: 20px; }
        }

        @media (max-width: 480px) {
            .header h1 { 
                flex-direction: column; 
                gap: 10px; 
                text-align: center; 
            }
            .code-snippet .line-number { min-width: 40px; }
            .details-box th { width: 40%; }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Background particles -->
    <div class="bg-particles">
        <div class="particle" style="left: 10%; animation-delay: 0s;"></div>
        <div class="particle" style="left: 20%; animation-delay: 0.5s;"></div>
        <div class="particle" style="left: 30%; animation-delay: 1s;"></div>
        <div class="particle" style="left: 40%; animation-delay: 1.5s;"></div>
        <div class="particle" style="left: 50%; animation-delay: 2s;"></div>
        <div class="particle" style="left: 60%; animation-delay: 2.5s;"></div>
        <div class="particle" style="left: 70%; animation-delay: 3s;"></div>
        <div class="particle" style="left: 80%; animation-delay: 3.5s;"></div>
        <div class="particle" style="left: 90%; animation-delay: 4s;"></div>
    </div>

    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>
                    <div class="error-icon">
                        <img
                            src="{{ asset('images/logo/BaultPHP-icon.png') }}"
                            class="img-fluid rounded-top"
                            alt=""
                            style="border-radius: 50%; width: 60px; height: 60px;"
                        />
                        
                    </div>
                    {{ get_class($exception) }}
                </h1>
                <p>{{ $exception->getMessage() }}</p>
                <button id="copy-error-btn" title="Copy error details to clipboard">
                    <i class="fas fa-copy"></i>
                    <span>Copy</span>
                </button>
            </div>
        </div>
        
        <div class="content">
            <div class="exception-details">
                <p class="file-info">
                    <i class="fas fa-file-code"></i> Error occurred at file <strong>{{ $exception->getFile() }}</strong> on line <strong>{{ $exception->getLine() }}</strong>
                </p>
            </div>

            @if (!empty($codeSnippet))
                <div class="code-snippet-container">
                    <div class="code-header">
                        <div class="filename">
                            <i class="fas fa-code"></i>
                            {{ basename($exception->getFile()) }}
                        </div>
                        <div class="line-info">
                            <i class="fas fa-crosshairs"></i>
                            Line {{ $exception->getLine() }}
                        </div>
                    </div>
                    <div class="code-snippet">
                        <pre><code>@foreach ($codeSnippet as $line)
<div class="line @if($line['number'] == $exception->getLine()) is-error @endif">
    <span class="line-number">{{ $line['number'] }}</span>
    <span class="line-content">{!! $line['content'] !!}</span>
</div>
@endforeach</code></pre>
                    </div>
                </div>
            @endif

            <h2 class="section-title">
                <i class="fas fa-layer-group"></i>
                Stack Trace
            </h2>
            <div class="stack-trace">
                <ol>
                    @foreach ($exception->getTrace() as $frame)
                        <li>
                            @if (isset($frame['file']))
                                <span class="file">{{ $frame['file'] }}</span>:<span class="line">{{ $frame['line'] }}</span><br>&nbsp;&nbsp;&nbsp;
                            @endif
                            @if (isset($frame['class']))
                                <span class="class">{{ $frame['class'] }}</span><span class="type">{{ $frame['type'] }}</span>
                            @endif
                            <span class="function">{{ $frame['function'] }}()</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <h2 class="section-title" style="margin-top: 50px;">
                <i class="fas fa-info-circle"></i>
                Request Details
            </h2>
            <div class="details-grid">
                <div class="details-box">
                    <h3>
                        <i class="fas fa-globe"></i>
                        Headers
                    </h3>
                    <table>
                        @foreach ($request['headers'] as $key => $values)
                            <tr>
                                <th>{{ $key }}</th>
                                <td>{{ implode(', ', $values) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div class="details-box">
                    <h3>
                        <i class="fas fa-search"></i>
                        Query String
                    </h3>
                    <table>
                        @foreach ($request['query'] as $key => $value)
                            <tr>
                                <th>{{ $key }}</th>
                                <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div class="details-box">
                    <h3>
                        <i class="fas fa-envelope"></i>
                        Request Body
                    </h3>
                    <table>
                        @foreach ((array) $request['body'] as $key => $value)
                            <tr>
                                <th>{{ $key }}</th>
                                <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="error-details-for-copy" style="display: none;">
    ## {{ get_class($exception) }}: {{ $exception->getMessage() }}

    **Location:**
    {{ $exception->getFile() }} on line {{ $exception->getLine() }}

    **Stack Trace:**
    @foreach ($exception->getTrace() as $index => $frame)
    #{{ $index }} {{ $frame['file'] ?? '[internal function]' }}{{ isset($frame['line']) ? ':' . $frame['line'] : '' }}
        {{ (isset($frame['class']) ? $frame['class'] . $frame['type'] : '') . $frame['function'] . '()' }}
    @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const copyBtn = document.getElementById('copy-error-btn');
            const errorDetailsText = document.getElementById('error-details-for-copy').textContent;

            if (copyBtn && navigator.clipboard) {
                copyBtn.addEventListener('click', function () {
                    navigator.clipboard.writeText(errorDetailsText.trim()).then(function() {
                        const buttonText = copyBtn.querySelector('span');
                        const originalText = buttonText.textContent;
                        copyBtn.querySelector('i').className = 'fas fa-check';
                        buttonText.textContent = 'Copied!';
                        copyBtn.disabled = true;

                        setTimeout(function() {
                            copyBtn.querySelector('i').className = 'fas fa-copy';
                            buttonText.textContent = originalText;
                            copyBtn.disabled = false;
                        }, 2000);
                    }).catch(err => console.error('Could not copy error: ', err));
                });
            } else if (copyBtn) {
                copyBtn.style.display = 'none'; 
            }
        });
    </script>
</body>
</html>