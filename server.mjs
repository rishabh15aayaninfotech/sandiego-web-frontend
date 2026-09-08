import { createServer } from "node:http";
import { readFile } from "node:fs/promises";
import path from "node:path";

const port = Number(process.env.PORT || 5173);
const root = process.cwd();
const types = new Map([
  [".html", "text/html; charset=utf-8"],
  [".shtml", "text/html; charset=utf-8"],
  [".txt", "text/plain; charset=utf-8"],
  [".xml", "application/xml; charset=utf-8"],
  [".css", "text/css; charset=utf-8"],
  [".js", "text/javascript; charset=utf-8"],
  [".png", "image/png"],
  [".jpg", "image/jpeg"],
  [".jpeg", "image/jpeg"],
  [".svg", "image/svg+xml"],
]);
const securityHeaders = {
  "Strict-Transport-Security": "max-age=31536000; includeSubDomains; preload",
};
const crawlerFileHeaders = {
  "Cache-Control": "no-cache, no-store, must-revalidate",
};

createServer(async (req, res) => {
  const urlPath = decodeURIComponent((req.url || "/").split("?")[0]);
  const queryString = (req.url || "").includes("?") ? `?${(req.url || "").split("?").slice(1).join("?")}` : "";
  const host = (req.headers.host || "").split(":")[0].toLowerCase();

  if (host === "sandiegodoorandwindow.com" || urlPath === "/index.shtml") {
    const redirectPath = urlPath === "/index.shtml" ? "/" : urlPath;
    res.writeHead(301, {
      ...securityHeaders,
      Location: `https://www.sandiegodoorandwindow.com${redirectPath}${queryString}`,
    });
    res.end();
    return;
  }

  const filePath = path.resolve(root, urlPath === "/" ? "index.shtml" : `.${urlPath}`);

  if (!filePath.startsWith(root)) {
    res.writeHead(403, securityHeaders);
    res.end("Forbidden");
    return;
  }

  try {
    const body = await readFile(filePath);
    const isCrawlerFile = urlPath === "/robots.txt" || urlPath === "/sitemap.xml" || urlPath === "/llms.txt";
    res.writeHead(200, {
      ...securityHeaders,
      ...(isCrawlerFile ? crawlerFileHeaders : {}),
      "Content-Type": types.get(path.extname(filePath)) || "application/octet-stream",
    });
    res.end(body);
  } catch {
    res.writeHead(404, securityHeaders);
    res.end("Not found");
  }
}).listen(port, "127.0.0.1", () => {
  console.log(`http://127.0.0.1:${port}`);
});
