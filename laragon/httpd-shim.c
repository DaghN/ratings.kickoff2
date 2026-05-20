/* Clear SSLKEYLOGFILE (Avast), then run httpd-real.exe. Config tests use exec;
   daemon mode keeps this process as httpd.exe (Laragon Stop All target). */
#include <windows.h>
#include <process.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static void build_cmdline(int argc, char *argv[], char *out, size_t out_size)
{
    size_t pos = 0;
    out[0] = '\0';
    for (int i = 1; i < argc; i++) {
        const char *arg = argv[i];
        int need_quote = (strchr(arg, ' ') != NULL || strchr(arg, '\t') != NULL);
        if (pos + 3 >= out_size) {
            break;
        }
        if (i > 1) {
            out[pos++] = ' ';
        }
        if (need_quote) {
            out[pos++] = '"';
            for (const char *p = arg; *p && pos + 2 < out_size; p++) {
                if (*p == '"') {
                    if (pos + 2 >= out_size) {
                        break;
                    }
                    out[pos++] = '\\';
                }
                out[pos++] = *p;
            }
            if (pos + 2 < out_size) {
                out[pos++] = '"';
            }
        } else {
            for (const char *p = arg; *p && pos + 1 < out_size; p++) {
                out[pos++] = *p;
            }
        }
        out[pos] = '\0';
    }
}

static int is_daemon_mode(int argc, char *argv[])
{
    for (int i = 1; i < argc; i++) {
        if (strcmp(argv[i], "-d") == 0) {
            return 1;
        }
    }
    return 0;
}

int main(int argc, char *argv[])
{
    char real_path[MAX_PATH];
    char cmdline[32768];
    char **new_argv;
    STARTUPINFOA si;
    PROCESS_INFORMATION pi;
    DWORD exit_code = 1;
    int i;

    SetEnvironmentVariableA("SSLKEYLOGFILE", NULL);

    if (GetModuleFileNameA(NULL, real_path, MAX_PATH) == 0) {
        return 1;
    }
    {
        char *slash = strrchr(real_path, '\\');
        if (!slash) {
            return 1;
        }
        strcpy(slash + 1, "httpd-real.exe");
    }

    if (!is_daemon_mode(argc, argv)) {
        new_argv = (char **)calloc((size_t)argc + 1, sizeof(char *));
        if (!new_argv) {
            return 1;
        }
        new_argv[0] = real_path;
        for (i = 1; i < argc; i++) {
            new_argv[i] = argv[i];
        }
        new_argv[argc] = NULL;
        _execv(real_path, (const char *const *)new_argv);
        fprintf(stderr, "httpd shim: exec failed for %s (errno %d)\n", real_path, errno);
        free(new_argv);
        return 1;
    }

    build_cmdline(argc, argv, cmdline, sizeof(cmdline));

    ZeroMemory(&si, sizeof(si));
    si.cb = sizeof(si);
    ZeroMemory(&pi, sizeof(pi));

    if (!CreateProcessA(
            real_path,
            cmdline[0] ? cmdline : NULL,
            NULL,
            NULL,
            FALSE,
            CREATE_NO_WINDOW,
            NULL,
            NULL,
            &si,
            &pi)) {
        return (int)GetLastError();
    }

    CloseHandle(pi.hThread);
    WaitForSingleObject(pi.hProcess, INFINITE);
    GetExitCodeProcess(pi.hProcess, &exit_code);
    CloseHandle(pi.hProcess);
    return (int)exit_code;
}
