<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Display all settings
     */
    public function index()
    {
        try {
            $settings = Setting::all()->pluck('value', 'key');
            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres'
            ], 500);
        }
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array'
        ]);

        try {
            foreach ($request->settings as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Paramètres mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des paramètres'
            ], 500);
        }
    }

    /**
     * Create a backup
     */
    public function backup()
    {
        try {
            // Créer un backup de la base de données
            $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = storage_path('app/backups/' . $backupName);

            // Commande mysqldump pour créer le backup
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.host'),
                config('database.connections.mysql.database'),
                $backupPath
            );

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new \Exception('Erreur lors de la sauvegarde');
            }

            return response()->json([
                'success' => true,
                'message' => 'Sauvegarde créée avec succès',
                'filename' => $backupName
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore from backup
     */
    public function restore(Request $request)
    {
        $request->validate([
            'filename' => 'required|string'
        ]);

        try {
            $backupPath = storage_path('app/backups/' . $request->filename);

            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier de sauvegarde introuvable'
                ], 404);
            }

            // Commande mysql restore
            $command = sprintf(
                'mysql --user=%s --password=%s --host=%s %s < %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.host'),
                config('database.connections.mysql.database'),
                $backupPath
            );

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new \Exception('Erreur lors de la restauration');
            }

            return response()->json([
                'success' => true,
                'message' => 'Restauration effectuée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration: ' . $e->getMessage()
            ], 500);
        }
    }
}
