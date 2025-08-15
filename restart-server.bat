@echo off
SETLOCAL

REM --- Cấu hình ---
SET PORT=9501
SET PHP_COMMAND=php cli serve:start

echo Dang kiem tra cong %PORT%...

REM Tìm PID của tiến trình đang ở trạng thái LISTENING trên cổng được chỉ định
FOR /F "tokens=5" %%P IN ('netstat -ano ^| findstr :%PORT% ^| findstr "LISTENING"') DO (
    SET PID=%%P
)

IF DEFINED PID (
    echo Tim thay tien trinh cu dang chay voi PID: %PID%. Dang dung...
    taskkill /PID %PID% /F
) ELSE (
    echo Cong %PORT% da san sang.
)

echo Dang khoi dong server BaultPHP...
%PHP_COMMAND%

ENDLOCAL
