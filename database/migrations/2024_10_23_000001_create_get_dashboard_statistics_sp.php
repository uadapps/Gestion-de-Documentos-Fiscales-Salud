<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear el stored procedure para estadísticas del dashboard
        DB::unprepared("
            CREATE PROCEDURE GetDashboardStatistics
                @ID_Empleado VARCHAR(20)
            AS
            BEGIN
                SET NOCOUNT ON;

                WITH campus_user AS (
                  SELECT DISTINCT CC.ID_Campus AS campus_id
                  FROM Campus_Contadores CC
                  WHERE CC.ID_Empleado = @ID_Empleado
                ),
                salud_vigente AS (
                  SELECT DISTINCT
                      E.ID_Campus       AS campus_id,
                      E.ID_Especialidad AS carrera_id
                  FROM Campus C
                  JOIN especialidades E ON E.ID_Campus = C.ID_Campus
                  JOIN Tipo_Plan TP     ON TP.Id_TipoPlan = E.TipoUniv
                  JOIN RVOE R           ON R.Id_Campus = E.ID_Campus AND R.Id_Especialidad = E.ID_Especialidad
                  JOIN Grupo G          ON G.ID_RVOE = R.Id_RVOE
                  JOIN Ciclo_Escolar CE ON CE.Id_CEscolar = G.ID_Periodo
                  WHERE (E.Descripcion LIKE '%MediC%' OR E.Descripcion LIKE '%ENFER%'
                      OR E.Descripcion LIKE '%NUTRI%' OR E.Descripcion LIKE '%FISIO%'
                      OR E.Descripcion LIKE '%ODONTO%' OR E.Descripcion LIKE '%COSME%')
                    AND E.Descripcion NOT LIKE '%DIPLO%'
                    AND E.Descripcion NOT LIKE '%MAES%'
                    AND E.Activada = 1
                    AND C.Activo   = 1
                    AND YEAR(CE.Fecha_Inicio) >= YEAR(GETDATE())
                ),
                docs_aplican AS (
                  SELECT
                      sd.id  AS documento_id,
                      sd.nombre,
                      sd.aplica_area_salud,
                      CASE WHEN sd.aplica_area_salud = 1 THEN 'MEDICINA' ELSE 'FISCAL' END AS tipo_documento
                  FROM sug_documentos sd
                  WHERE sd.activo = 1
                ),
                esperados AS (
                  SELECT cu.campus_id, d.documento_id, sv.carrera_id, d.aplica_area_salud, d.tipo_documento
                  FROM campus_user cu
                  JOIN docs_aplican d ON d.aplica_area_salud = 1
                  JOIN salud_vigente sv ON sv.campus_id = cu.campus_id

                  UNION ALL

                  SELECT cu.campus_id, d.documento_id, NULL AS carrera_id, d.aplica_area_salud, d.tipo_documento
                  FROM campus_user cu
                  JOIN docs_aplican d ON d.aplica_area_salud = 0
                ),
                sdi_norm AS (
                  SELECT
                    sdi.campus_id,
                    sdi.documento_id,
                    CASE WHEN d.aplica_area_salud = 1 THEN sdi.carrera_id ELSE NULL END AS carrera_id,
                    sdi.estado
                  FROM sug_documentos_informacion sdi
                  JOIN docs_aplican d  ON d.documento_id = sdi.documento_id
                  JOIN campus_user cu  ON cu.campus_id   = sdi.campus_id
                ),
                sdi_collapse_rank AS (
                  SELECT
                    sn.campus_id, sn.documento_id, sn.carrera_id,
                    MAX(CASE UPPER(LTRIM(RTRIM(sn.estado)))
                          WHEN 'VIGENTE'   THEN 3
                          WHEN 'RECHAZADO' THEN 2
                          WHEN 'PENDIENTE' THEN 1
                          ELSE 0
                        END) AS estado_rank
                  FROM sdi_norm sn
                  GROUP BY sn.campus_id, sn.documento_id, sn.carrera_id
                ),
                sdi_collapse AS (
                  SELECT
                    campus_id, documento_id, carrera_id,
                    CASE estado_rank
                      WHEN 3 THEN 'VIGENTE'
                      WHEN 2 THEN 'RECHAZADO'
                      WHEN 1 THEN 'PENDIENTE'
                      ELSE 'PENDIENTE'
                    END AS estado_norm
                  FROM sdi_collapse_rank
                ),
                resultado_base AS (
                  SELECT
                    e.campus_id,
                    e.documento_id,
                    e.carrera_id,
                    e.aplica_area_salud,
                    e.tipo_documento,
                    ISNULL(sc.estado_norm, 'PENDIENTE') AS estado_final
                  FROM esperados e
                  LEFT JOIN sdi_collapse sc
                    ON sc.campus_id   = e.campus_id
                   AND sc.documento_id = e.documento_id
                   AND (
                        (e.aplica_area_salud = 1 AND sc.carrera_id = e.carrera_id) OR
                        (e.aplica_area_salud = 0 AND sc.carrera_id IS NULL)
                       )
                )
                -- RESULTADO FINAL: Totales por campus y tipo (Fiscal / Medicina)
                SELECT
                  rb.campus_id,
                  rb.tipo_documento,
                  SUM(CASE WHEN rb.estado_final = 'VIGENTE'   THEN 1 ELSE 0 END) AS Vigentes,
                  SUM(CASE WHEN rb.estado_final = 'RECHAZADO' THEN 1 ELSE 0 END) AS Rechazados,
                  SUM(CASE WHEN rb.estado_final = 'PENDIENTE' THEN 1 ELSE 0 END) AS Pendientes,
                  COUNT(*) AS Total
                FROM resultado_base rb
                GROUP BY rb.campus_id, rb.tipo_documento
                ORDER BY rb.campus_id, rb.tipo_documento;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS GetDashboardStatistics");
    }
};
