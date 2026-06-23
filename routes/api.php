<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EstructuraPresupuestariaController;
use App\Http\Controllers\CedulaPresupuestariaController;
use App\Http\Controllers\CertificacionController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\EntidadRequirienteController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\AuditoriaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas de autenticación públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Rutas protegidas con middleware de token personalizado
Route::middleware('validate.custom.token')->group(function () {
    // Datos para cascadas del formulario de certificación
    Route::get('/certificacion/programas', [CertificacionController::class, 'getProgramas']);
    Route::get('/certificacion/subprogramas/{idPrograma}', [CertificacionController::class, 'getSubprogramas']);
    Route::get('/certificacion/proyectos/{idSubprograma}', [CertificacionController::class, 'getProyectos']);
    Route::get('/certificacion/actividades/{idProyecto}', [CertificacionController::class, 'getActividades']);
    Route::get('/certificacion/items-by-actividad-fuente/{idActividad}', [CertificacionController::class, 'getItemsByActividadFuente']);
    Route::get('/certificacion/fuentes', [CertificacionController::class, 'getFuentes']);
    Route::get('/certificacion/fuentes/{idActividad}', [CertificacionController::class, 'getFuentesByActividad']);
    Route::get('/certificacion/ubicaciones/{idActividad}', [CertificacionController::class, 'getUbicaciones']);
    Route::get('/certificacion/items/{idActividad}/{idUbicacion}', [CertificacionController::class, 'getItems']);
    Route::get('/certificacion/items/{idActividad}/{idUbicacion}/{idFuente}', [CertificacionController::class, 'getItemsByFuente']);
    Route::get('/certificacion/organismos', [CertificacionController::class, 'getOrganismos']);
    Route::get('/certificacion/naturalezas', [CertificacionController::class, 'getNaturalezas']);
    Route::get('/certificacion/unidades-requirientes', [CertificacionController::class, 'getEntidadesRequirientes']);
    Route::get('/certificacion/cedulas-presupuestarias', [CertificacionController::class, 'getCedulasPresupuestarias']);
    Route::get('/certificacion/cedula-actual', [CertificacionController::class, 'getCedulaActual']);
    Route::get('/certificacion/verificar-monto/{idItem}/{idFuente}', [CertificacionController::class, 'verificarMontoDisponible']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Estructura Presupuestaria — solo Director/Admin
    Route::middleware('check.role:directores')->group(function () {
        Route::post('/estructura-presupuestaria/upload', [EstructuraPresupuestariaController::class, 'upload']);
        Route::get('/estructura-presupuestaria/summary', [EstructuraPresupuestariaController::class, 'summary']);
        Route::get('/estructura-presupuestaria/data', [EstructuraPresupuestariaController::class, 'getData']);
    });

    // Cédula Presupuestaria — upload solo Director/Admin; lectura para todos los roles
    Route::middleware('check.role:directores')->post('/cedula-presupuestaria/upload', [CedulaPresupuestariaController::class, 'upload']);
    Route::get('/cedula-presupuestaria/summary', [CedulaPresupuestariaController::class, 'summary']);
    Route::get('/cedula-presupuestaria/data', [CedulaPresupuestariaController::class, 'getData']);

    // Presupuesto disponible (HU-09)
    Route::get('/presupuesto-disponible', [PresupuestoController::class, 'index']);
    Route::get('/presupuesto/certificaciones-por-item', [PresupuestoController::class, 'certificacionesPorItem']);
    
    // Rutas CRUD de Usuarios
    Route::get('/usuarios', [UserController::class, 'index']);
    Route::post('/usuarios', [UserController::class, 'store']);
    Route::get('/usuarios/{id}', [UserController::class, 'show']);
    Route::put('/usuarios/{id}', [UserController::class, 'update']);
    Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
    Route::post('/usuarios/{id}/desbloquear', [UserController::class, 'desbloquear']);
    
    // Rutas CRUD de Certificación
    Route::get('/certificacion', [CertificacionController::class, 'index']);
    Route::post('/certificacion', [CertificacionController::class, 'store']);
    Route::get('/certificacion/{id}', [CertificacionController::class, 'show']);
    Route::put('/certificacion/{id}', [CertificacionController::class, 'update']);
    Route::delete('/certificacion/{id}', [CertificacionController::class, 'destroy']);
    
    // Rutas para agregar/remover items
    Route::post('/certificacion/{id}/agregar-item', [CertificacionController::class, 'agregarItem']);
    Route::patch('/certificacion/{idCertificacion}/item/{idItem}', [CertificacionController::class, 'actualizarItem']);
    Route::delete('/certificacion/{idCertificacion}/item/{idItem}', [CertificacionController::class, 'removerItem']);

    // Rutas de flujo de aprobación (director/analista)
    Route::patch('/certificacion/{id}/aprobar',  [CertificacionController::class, 'aprobar']);
    Route::patch('/certificacion/{id}/rechazar', [CertificacionController::class, 'rechazar']);
    Route::patch('/certificacion/{id}/reenviar', [CertificacionController::class, 'reenviar']);
    Route::patch('/certificacion/{id}/errar',    [CertificacionController::class, 'errar']);

    // Rutas para crear unidades requirientes y cédulas
    Route::post('/certificacion/unidades-requirientes', [CertificacionController::class, 'createEntidadRequiriente']);
    Route::post('/certificacion/cedulas-presupuestarias', [CertificacionController::class, 'createCedulaPresupuestaria']);

    // Rutas de Liquidaciones
    Route::get('/liquidaciones/certificaciones',     [LiquidacionController::class, 'certificaciones']);
    Route::get('/liquidaciones/certificacion-items', [LiquidacionController::class, 'certificacionItems']);
    Route::get('/liquidaciones',                     [LiquidacionController::class, 'index']);
    Route::post('/liquidaciones',                    [LiquidacionController::class, 'store']);
    Route::delete('/liquidaciones/{id}',             [LiquidacionController::class, 'destroy']);
    Route::patch('/liquidaciones/{id}/anular',       [LiquidacionController::class, 'anular']);

    // Rutas de Unidades Requirientes (HU-08)
    Route::get('/unidades-requirientes',            [EntidadRequirienteController::class, 'index']);
    Route::post('/unidades-requirientes',           [EntidadRequirienteController::class, 'store']);
    Route::get('/unidades-requirientes/{id}',       [EntidadRequirienteController::class, 'show']);
    Route::put('/unidades-requirientes/{id}',       [EntidadRequirienteController::class, 'update']);
    Route::delete('/unidades-requirientes/{id}',    [EntidadRequirienteController::class, 'destroy']);

    // Rutas de Reportes CSV (HU-15)
    Route::get('/reportes/certificaciones/csv',      [ReporteController::class, 'certificacionesCsv']);
    Route::get('/reportes/liquidaciones/csv',        [ReporteController::class, 'liquidacionesCsv']);
    Route::get('/reportes/presupuesto/csv',          [ReporteController::class, 'presupuestoCsv']);
    // Rutas de Auditoría (HU-17)
    Route::get('/auditoria',                          [AuditoriaController::class, 'index']);
    Route::get('/auditoria/certificacion/{id}',       [AuditoriaController::class, 'porCertificacion']);

    // Rutas de Reportes JSON para PDF/impresión
    Route::get('/reportes/certificaciones/json',     [ReporteController::class, 'certificacionesJson']);
    Route::get('/reportes/liquidaciones/json',       [ReporteController::class, 'liquidacionesJson']);
    Route::get('/reportes/presupuesto/json',         [ReporteController::class, 'presupuestoJson']);
    Route::get('/reportes/auditoria/csv',            [ReporteController::class, 'auditoriaCsv']);
    Route::get('/reportes/auditoria/json',           [ReporteController::class, 'auditoriaJson']);
});


