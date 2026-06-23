<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\EnviaCorreoHtml;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use EnviaCorreoHtml;

    private static array $rolesPermitidos = ['Director(a) financiero', 'Administrador del sistema'];

    private function denegarSiNoEsAdmin(): ?\Illuminate\Http\JsonResponse
    {
        if (!in_array(Auth::user()?->cargo, self::$rolesPermitidos)) {
            return response()->json(['status' => 'error', 'message' => 'No tiene permiso para gestionar usuarios'], 403);
        }
        return null;
    }

    /**
     * Obtener lista de todos los usuarios
     */
    public function index()
    {
        if ($deny = $this->denegarSiNoEsAdmin()) return $deny;
        try {
            $usuarios = User::select(
                'id_usuario',
                'nombres',
                'apellidos',
                'correo_institucional',
                'cargo',
                'estado',
                'created_at'
            )->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Usuarios obtenidos correctamente',
                'data' => $usuarios
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo usuario
     */
    public function store(Request $request)
    {
        if ($deny = $this->denegarSiNoEsAdmin()) return $deny;
        try {
            $validated = $request->validate([
                'nombres'              => 'required|string|max:100',
                'apellidos'            => 'required|string|max:100',
                'correo_institucional' => 'required|string|email|max:100|unique:usuarios,correo_institucional',
                'cargo'                => 'required|string|in:Director(a) financiero,Analista de presupuesto 1,Analista de presupuesto 3,Director(a) de talento humano,Rector',
                'estado'               => 'required|string|in:activo,inactivo,bloqueado',
            ]);

            $contrasenaTemp = $this->generarContrasenaAleatoria();

            $usuario = User::create([
                'nombres'              => $validated['nombres'],
                'apellidos'            => $validated['apellidos'],
                'correo_institucional' => $validated['correo_institucional'],
                'contrasena'           => Hash::make($contrasenaTemp),
                'cargo'                => $validated['cargo'],
                'estado'               => $validated['estado'],
                'contrasena_temporal'  => true,
            ]);

            try {
                $this->enviarCredencialesEmail($usuario, $contrasenaTemp);
            } catch (\Exception $mailEx) {
                $usuario->delete();
                \Log::error('Error enviando credenciales: ' . $mailEx->getMessage());
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No se pudo enviar el correo con las credenciales. Verifique la configuración de correo.',
                ], 500);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario creado. Se envió un correo con las credenciales de acceso.',
                'data'    => [
                    'id_usuario'           => $usuario->id_usuario,
                    'nombres'              => $usuario->nombres,
                    'apellidos'            => $usuario->apellidos,
                    'correo_institucional' => $usuario->correo_institucional,
                    'cargo'                => $usuario->cargo,
                    'estado'               => $usuario->estado,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error de validación',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function generarContrasenaAleatoria(int $length = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    private function enviarCredencialesEmail(User $usuario, string $contrasenaTemp): void
    {
        $nombreCompleto = trim("{$usuario->nombres} {$usuario->apellidos}");
        $asunto = 'Bienvenido/a al Sistema de Control Presupuestario — Credenciales de Acceso';

        $cuerpo = "Estimado/a {$nombreCompleto},\n\n"
            . "Me permito comunicarle que su cuenta en el Sistema de Control Presupuestario "
            . "de la Universidad Estatal de Bolívar ha sido creada exitosamente.\n\n"
            . "A continuación se detallan sus credenciales de acceso. Le recomendamos guardar "
            . "esta información en un lugar seguro y no compartirla con terceros.\n\n"
            . "IMPORTANTE: Al ingresar por primera vez, el sistema le solicitará que establezca "
            . "una nueva contraseña personal. Por razones de seguridad, le pedimos cambiarla de inmediato.\n\n"
            . "Atentamente,\n"
            . "Sistema de Control Presupuestario\n"
            . "Universidad Estatal de Bolívar";

        $extras = $this->bloqueCredenciales(
            $usuario->correo_institucional,
            $contrasenaTemp,
            $usuario->cargo
        );

        $html = $this->plantillaHtml($asunto, $cuerpo, $extras);

        Mail::html($html, function ($message) use ($usuario, $nombreCompleto, $asunto) {
            $message->to($usuario->correo_institucional, $nombreCompleto)
                    ->subject($asunto);
        });
    }

    /**
     * Obtener usuario por ID
     */
    public function show($id)
    {
        if ($deny = $this->denegarSiNoEsAdmin()) return $deny;
        try {
            $usuario = User::find($id);

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario obtenido correctamente',
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombres' => $usuario->nombres,
                    'apellidos' => $usuario->apellidos,
                    'correo_institucional' => $usuario->correo_institucional,
                    'cargo' => $usuario->cargo,
                    'estado' => $usuario->estado,
                    'created_at' => $usuario->created_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, $id)
    {
        if ($deny = $this->denegarSiNoEsAdmin()) return $deny;
        try {
            $usuario = User::find($id);

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Validar datos
            $validated = $request->validate([
                'nombres' => 'sometimes|required|string|max:100',
                'apellidos' => 'sometimes|required|string|max:100',
                'correo_institucional' => 'sometimes|required|string|email|max:100|unique:usuarios,correo_institucional,' . $id . ',id_usuario',
                'contrasena' => 'sometimes|nullable|string|min:6',
                'cargo' => 'sometimes|required|string|in:Director(a) financiero,Analista de presupuesto 1,Analista de presupuesto 3,Director(a) de talento humano,Rector',
                'estado' => 'sometimes|required|string|in:activo,inactivo,bloqueado'
            ]);

            // Actualizar campos
            if (isset($validated['nombres'])) $usuario->nombres = $validated['nombres'];
            if (isset($validated['apellidos'])) $usuario->apellidos = $validated['apellidos'];
            if (isset($validated['correo_institucional'])) $usuario->correo_institucional = $validated['correo_institucional'];
            if (isset($validated['contrasena']) && !empty($validated['contrasena'])) {
                $usuario->contrasena          = Hash::make($validated['contrasena']);
                $usuario->contrasena_temporal = true; // el usuario debe cambiarla al entrar
            }
            if (isset($validated['cargo'])) $usuario->cargo = $validated['cargo'];
            if (isset($validated['estado'])) $usuario->estado = $validated['estado'];

            $usuario->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario actualizado correctamente',
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombres' => $usuario->nombres,
                    'apellidos' => $usuario->apellidos,
                    'correo_institucional' => $usuario->correo_institucional,
                    'cargo' => $usuario->cargo,
                    'estado' => $usuario->estado
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desbloquear cuenta bloqueada por intentos fallidos
     */
    public function desbloquear($id)
    {
        if ($deny = $this->denegarSiNoEsAdmin()) return $deny;
        try {
            $usuario = User::find($id);
            if (!$usuario) {
                return response()->json(['status' => 'error', 'message' => 'Usuario no encontrado'], 404);
            }
            $usuario->estado            = 'activo';
            $usuario->intentos_fallidos = 0;
            $usuario->save();
            return response()->json([
                'status'  => 'success',
                'message' => 'Cuenta desbloqueada correctamente',
                'data'    => ['id_usuario' => $usuario->id_usuario, 'estado' => $usuario->estado],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al desbloquear: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar usuario
     */
    public function destroy($id)
    {
        if ($deny = $this->denegarSiNoEsAdmin()) return $deny;
        try {
            $usuario = User::find($id);

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            $usuario->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar usuario: ' . $e->getMessage()
            ], 500);
        }
    }
}
