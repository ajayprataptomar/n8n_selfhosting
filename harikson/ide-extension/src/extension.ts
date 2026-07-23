import * as vscode from 'vscode';
import { io, Socket } from 'socket.io-client';

let socket: Socket | undefined;

export function activate(context: vscode.ExtensionContext) {
  console.log('⚡ [Neuravolt AI] VS Code Extension activated!');

  const config = vscode.workspace.getConfiguration('neuravolt');
  const agentUrl = config.get<string>('agentUrl') || 'http://localhost:6000';
  const apiKey = config.get<string>('apiKey') || '';

  // Connect to the isolated IDE Bridge Socket
  socket = io(agentUrl, {
    auth: { token: apiKey },
    transports: ['websocket'],
  });

  socket.on('connect', () => {
    vscode.window.showInformationMessage(
      '🔌 [Neuravolt AI] Connected to coding bridge agent.'
    );
  });

  socket.on('disconnect', () => {
    vscode.window.showWarningMessage(
      '🔌 [Neuravolt AI] Disconnected from coding bridge.'
    );
  });

  // 1. Register Inline Autocomplete Provider (Ghost Text)
  const provider: vscode.InlineCompletionItemProvider = {
    async provideInlineCompletionItems(document, position, context, token) {
      if (!socket || !socket.connected) return undefined;

      const lineText = document.lineAt(position.line).text;
      const prefix = document.getText(
        new vscode.Range(new vscode.Position(0, 0), position)
      );
      const suffix = document.getText(
        new vscode.Range(position, new vscode.Position(document.lineCount, 0))
      );

      // Request suggestion from IDE bridge via promise wrapper
      try {
        const suggestion = await new Promise<{ completion: string }>(
          (resolve, reject) => {
            socket!.emit(
              'autocomplete',
              {
                prefix,
                suffix,
                language: document.languageId,
              },
              (response: any) => {
                if (response && response.completion) {
                  resolve(response);
                } else {
                  reject();
                }
              }
            );

            // Timeout check
            setTimeout(() => reject(), 250);
          }
        );

        const completionItem = new vscode.InlineCompletionItem(
          suggestion.completion
        );
        return [completionItem];
      } catch {
        return undefined;
      }
    },
  };

  context.subscriptions.push(
    vscode.languages.registerInlineCompletionItemProvider(
      { pattern: '**' },
      provider
    )
  );

  // 2. Register Webview Chat view
  context.subscriptions.push(
    vscode.window.registerWebviewViewProvider('neuravolt-chat-view', {
      resolveWebviewView(webviewView) {
        webviewView.webview.options = { enableScripts: true };
        webviewView.webview.html = `
          <!DOCTYPE html>
          <html>
          <body style="font-family: sans-serif; padding: 10px; color: white; background: #1e1e1e;">
            <h3>Neuravolt Code Assistant</h3>
            <div id="messages" style="height: 250px; overflow-y: auto; font-size: 0.85rem; border: 1px solid #333; padding: 8px; border-radius: 4px; background: #000; margin-bottom: 10px;">
              <div>Ask a question to start.</div>
            </div>
            <div style="display: flex; gap: 6px;">
              <input id="input" type="text" placeholder="Ask AI..." style="flex: 1; padding: 6px; font-size: 0.8rem; background: #333; border: 1px solid #444; border-radius: 4px; color: white;" />
              <button id="send" style="background: #8b5cf6; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer;">Ask</button>
            </div>
          </body>
          </html>
        `;
      },
    })
  );
}

export function deactivate() {
  if (socket) {
    socket.disconnect();
  }
}
