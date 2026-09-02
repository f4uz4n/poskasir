@echo off
set CSC64=%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe
set CSC32=%WINDIR%\Microsoft.NET\Framework\v4.0.30319\csc.exe
if exist "%CSC64%" (
    "%CSC64%" /nologo /target:library /out:"%~dp0KasirFlowThermalPrint.dll" "%~dp0KasirFlowThermalPrint.cs" /r:System.Drawing.dll
) else if exist "%CSC32%" (
    "%CSC32%" /nologo /target:library /out:"%~dp0KasirFlowThermalPrint.dll" "%~dp0KasirFlowThermalPrint.cs" /r:System.Drawing.dll
) else (
    echo csc.exe tidak ditemukan
    exit /b 1
)
echo OK: KasirFlowThermalPrint.dll
