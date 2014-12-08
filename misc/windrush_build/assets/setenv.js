var shell = WScript.CreateObject("WScript.Shell");
var fs = new ActiveXObject("Scripting.FileSystemObject");

var PATH_KEY = "HKLM\\SYSTEM\\CurrentControlSet\\Control\\Session Manager\\Environment\\Path";
var path = shell.RegRead(PATH_KEY);
var windrush = fs.GetParentFolderName(WScript.ScriptFullName)

var inPath = path.toLowerCase().indexOf(windrush.toLowerCase()) != -1;

WScript.Echo("Adding '" + windrush + "' to your PATH variable...");
if (inPath) {
    WScript.Echo("'" + windrush + "' is already in your PATH variable");
} else {
    try {
        shell.RegWrite(PATH_KEY, windrush + ";" + path, "REG_EXPAND_SZ");
        var oExec = shell.Exec(windrush + "\\tools\\bin\\notify_env_change.exe");
        while (oExec.Status == 0)
             WScript.Sleep(100);
        if (oExec.ExitCode != 0)
            WScript.Echo("Failed to notify the system about PATH change. Reboot required");
        WScript.Echo("Done.");
    } catch (err) {
        WScript.Echo("Could not write PATH variable to the registry.\nYou may have insufficient permissions to that. Try running this script as administrator");
    }

}

