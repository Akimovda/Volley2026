// Cloudflare Worker «tg-relay»: релей Telegram для сервера, у которого нет прямого доступа к Telegram.
// Маршруты: /bot* → api.telegram.org; /webhook → наш /telegram/webhook; /oauth/token и /oauth/.well-known/jwks.json → oauth.telegram.org
// (вход через Telegram OIDC; включается TELEGRAM_OIDC_VIA_RELAY=true в .env сайта).
export default {
  async fetch(request) {
    const url = new URL(request.url);

    // 1. Проксирование ЗАПРОСОВ К TELEGRAM (ваш сервер -> Worker -> Telegram)
    if (url.pathname.startsWith('/bot')) {
      url.hostname = "api.telegram.org";
      return fetch(new Request(url, { method: request.method, headers: request.headers, body: request.body }));
    }

    // 2. Проксирование ВХОДЯЩИХ ВЕБХУКОВ (Telegram -> Worker -> ваш сервер)
    if (url.pathname === '/webhook') {
      const targetUrl = new URL('https://volleyplay.club/telegram/webhook');
      return fetch(new Request(targetUrl, { method: request.method, headers: request.headers, body: request.body }));
    }

    // 3. Вход через Telegram (OIDC): сервер -> Worker -> oauth.telegram.org.
    // Только обмен кода на токен и публичные ключи (JWKS); остальные пути oauth.telegram.org не проксируются.
    if (url.pathname === '/oauth/token' || url.pathname === '/oauth/.well-known/jwks.json') {
      url.hostname = "oauth.telegram.org";
      url.pathname = url.pathname.slice('/oauth'.length);
      return fetch(new Request(url, { method: request.method, headers: request.headers, body: request.body }));
    }

    return new Response("Not Found", { status: 404 });
  }
}
