<?php

namespace App\Traits;

use Illuminate\Support\Facades\Mail;

trait EnviaCorreoHtml
{
    protected function enviarCorreo(string $correo, string $nombre, string $asunto, string $cuerpo, string $extras = ''): void
    {
        try {
            $html = $this->plantillaHtml($asunto, $cuerpo, $extras);
            Mail::html($html, function ($msg) use ($correo, $nombre, $asunto) {
                $msg->to($correo, $nombre)->subject($asunto);
            });
        } catch (\Throwable $e) {
            \Log::warning("No se pudo enviar email a {$correo}: " . $e->getMessage());
        }
    }

    protected function plantillaHtml(string $asunto, string $cuerpo, string $extras = ''): string
    {
        $lineas = array_map(
            fn($l) => trim($l) === ''
                ? '<br>'
                : '<p style="margin:0 0 10px 0;">' . htmlspecialchars($l) . '</p>',
            explode("\n", $cuerpo)
        );
        $contenido = implode("\n", $lineas);
        $anio      = now()->year;

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1.0">
          <title>{$asunto}</title>
        </head>
        <body style="margin:0;padding:0;background-color:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4f8;padding:32px 0;">
            <tr>
              <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

                  <!-- ENCABEZADO -->
                  <tr>
                    <td style="background:linear-gradient(135deg,#0d2f5e 0%,#1a5276 100%);border-radius:12px 12px 0 0;padding:32px 40px;text-align:center;">
                      <p style="margin:0 0 4px 0;font-size:11px;letter-spacing:3px;color:#aed6f1;text-transform:uppercase;font-weight:600;">Universidad Estatal de Bolívar</p>
                      <h1 style="margin:0 0 4px 0;font-size:20px;color:#ffffff;font-weight:700;letter-spacing:0.5px;">Sistema de Control Presupuestario</h1>
                      <p style="margin:0;font-size:12px;color:#85c1e9;">Dirección Financiera</p>
                    </td>
                  </tr>

                  <!-- BANDA DE COLOR -->
                  <tr>
                    <td style="background:#2e86c1;height:4px;"></td>
                  </tr>

                  <!-- ASUNTO -->
                  <tr>
                    <td style="background:#ffffff;padding:28px 40px 16px 40px;border-left:1px solid #dce6f0;border-right:1px solid #dce6f0;">
                      <h2 style="margin:0;font-size:16px;color:#0d2f5e;font-weight:700;border-bottom:2px solid #2e86c1;padding-bottom:12px;">{$asunto}</h2>
                    </td>
                  </tr>

                  <!-- CUERPO -->
                  <tr>
                    <td style="background:#ffffff;padding:16px 40px 8px 40px;border-left:1px solid #dce6f0;border-right:1px solid #dce6f0;">
                      <div style="font-size:14px;color:#2c3e50;line-height:1.8;">
                        {$contenido}
                      </div>
                    </td>
                  </tr>

                  {$extras}

                  <!-- AVISO -->
                  <tr>
                    <td style="background:#eaf4fb;padding:16px 40px;border:1px solid #aed6f1;border-top:none;">
                      <p style="margin:0;font-size:12px;color:#1a5276;">
                        &#9432;&nbsp; Este es un mensaje automático generado por el Sistema de Control Presupuestario de la UEB.
                        Por favor, no responda a este correo.
                      </p>
                    </td>
                  </tr>

                  <!-- PIE -->
                  <tr>
                    <td style="background:#0d2f5e;border-radius:0 0 12px 12px;padding:20px 40px;text-align:center;">
                      <p style="margin:0 0 4px 0;font-size:12px;color:#aed6f1;font-weight:600;">Universidad Estatal de Bolívar — Dirección Financiera</p>
                      <p style="margin:0;font-size:11px;color:#5d8aa8;">Guaranda, Ecuador &nbsp;|&nbsp; &copy; {$anio}</p>
                    </td>
                  </tr>

                </table>
              </td>
            </tr>
          </table>
        </body>
        </html>
        HTML;
    }

    protected function bloqueCredenciales(string $correo, string $contrasena, string $cargo): string
    {
        return <<<HTML
                  <tr>
                    <td style="background:#ffffff;padding:4px 40px 24px 40px;border-left:1px solid #dce6f0;border-right:1px solid #dce6f0;">
                      <div style="background:#f0f4f8;border-left:4px solid #2e86c1;padding:18px 24px;border-radius:0 8px 8px 0;">
                        <p style="margin:0 0 14px 0;font-size:12px;color:#0d2f5e;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Credenciales de acceso</p>
                        <table cellpadding="0" cellspacing="0" style="font-size:13px;color:#2c3e50;width:100%;border-collapse:collapse;">
                          <tr>
                            <td style="padding:6px 0;font-weight:600;width:185px;vertical-align:top;color:#1a3a5c;">Usuario (correo):</td>
                            <td style="padding:6px 0;">{$correo}</td>
                          </tr>
                          <tr>
                            <td style="padding:6px 0;font-weight:600;vertical-align:top;color:#1a3a5c;">Contraseña temporal:</td>
                            <td style="padding:6px 0;font-family:'Courier New',monospace;font-size:15px;letter-spacing:2px;color:#0d2f5e;font-weight:700;">{$contrasena}</td>
                          </tr>
                          <tr>
                            <td style="padding:6px 0;font-weight:600;vertical-align:top;color:#1a3a5c;">Cargo asignado:</td>
                            <td style="padding:6px 0;">{$cargo}</td>
                          </tr>
                        </table>
                      </div>
                    </td>
                  </tr>
        HTML;
    }

    protected function botonAccion(string $url, string $texto = 'Restablecer mi contraseña'): string
    {
        $urlSafe   = htmlspecialchars($url);
        $textoSafe = htmlspecialchars($texto);

        return <<<HTML
                  <tr>
                    <td style="background:#ffffff;padding:8px 40px 28px 40px;border-left:1px solid #dce6f0;border-right:1px solid #dce6f0;text-align:center;">
                      <a href="{$urlSafe}"
                         style="display:inline-block;background:linear-gradient(135deg,#0d2f5e 0%,#2e86c1 100%);color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:14px;font-weight:700;letter-spacing:0.5px;">
                        {$textoSafe}
                      </a>
                      <p style="margin:18px 0 0;font-size:11px;color:#7f8c8d;line-height:1.7;">
                        Si el botón no funciona, copie y pegue el siguiente enlace en su navegador:<br>
                        <a href="{$urlSafe}" style="color:#2e86c1;word-break:break-all;font-size:11px;">{$urlSafe}</a>
                      </p>
                    </td>
                  </tr>
        HTML;
    }
}
