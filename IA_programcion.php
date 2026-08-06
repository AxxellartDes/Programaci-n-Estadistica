<?php

namespace App\Validators;

use DateTime;
use Exception;

/**
 * Validador de conflictos en la programación de operadores
 * Verifica disponibilidad, horas trabajadas, descanso mínimo y tipo de vehículo
 */
class ScheduleConflictValidator
{
    // Constantes de validación
    private const MAX_HOURS_PER_DAY = 8;              // Máximo de horas por día
    private const MAX_HOURS_PER_WEEK = 48;            // Máximo de horas por semana
    private const MIN_REST_HOURS = 12;                // Mínimo descanso entre turnos
    private const MIN_REST_DAILY = 8;                 // Mínimo descanso diario
    private const MEAL_BREAK_MINUTES = 30;            // Tiempo de descanso para comida

    private array $operatorSchedules = [];
    private array $vehicleAuthorizations = [];
    private array $operatorAvailability = [];
    private array $validationErrors = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->operatorSchedules = [];
        $this->vehicleAuthorizations = [];
        $this->operatorAvailability = [];
        $this->validationErrors = [];
    }

    /**
     * Registra las autorizaciones de vehículos por operador
     * 
     * @param int $operatorId
     * @param array $authorizedVehicles ['articulated_bus', 'standard_bus', 'feeder_bus']
     */
    public function setVehicleAuthorizations(int $operatorId, array $authorizedVehicles): void
    {
        $this->vehicleAuthorizations[$operatorId] = $authorizedVehicles;
    }

    /**
     * Registra la disponibilidad del operador
     * 
     * @param int $operatorId
     * @param array $availability [
     *     'monday' => ['start' => '06:00', 'end' => '22:00'],
     *     'tuesday' => ['start' => '06:00', 'end' => '22:00'],
     *     // ...
     * ]
     */
    public function setOperatorAvailability(int $operatorId, array $availability): void
    {
        $this->operatorAvailability[$operatorId] = $availability;
    }

    /**
     * Registra un turno programado para un operador
     * 
     * @param int $operatorId
     * @param array $schedule [
     *     'schedule_id' => 123,
     *     'start_time' => '2024-01-15 06:00:00',
     *     'end_time' => '2024-01-15 14:00:00',
     *     'vehicle_type' => 'articulated_bus',
     *     'route_id' => 45,
     *     'status' => 'confirmed' | 'pending'
     * ]
     */
    public function addSchedule(int $operatorId, array $schedule): void
    {
        if (!isset($this->operatorSchedules[$operatorId])) {
            $this->operatorSchedules[$operatorId] = [];
        }
        $this->operatorSchedules[$operatorId][] = $schedule;
    }

    /**
     * Valida un nuevo turno propuesto antes de asignarlo
     * 
     * @param int $operatorId
     * @param array $proposedSchedule
     * @return array ['valid' => bool, 'conflicts' => array, 'warnings' => array]
     */
    public function validateProposedSchedule(int $operatorId, array $proposedSchedule): array
    {
        $this->validationErrors = [];
        $conflicts = [];
        $warnings = [];

        // Validaciones críticas
        if (!$this->validateBasicData($operatorId, $proposedSchedule, $conflicts)) {
            return [
                'valid' => false,
                'conflicts' => $conflicts,
                'warnings' => []
            ];
        }

        // Validar autorización de vehículo
        if (!$this->validateVehicleAuthorization($operatorId, $proposedSchedule, $conflicts)) {
            return [
                'valid' => false,
                'conflicts' => $conflicts,
                'warnings' => []
            ];
        }

        // Validar disponibilidad
        if (!$this->validateAvailability($operatorId, $proposedSchedule, $conflicts)) {
            return [
                'valid' => false,
                'conflicts' => $conflicts,
                'warnings' => []
            ];
        }

        // Validar traslapes de horario
        if (!$this->validateTimeOverlap($operatorId, $proposedSchedule, $conflicts)) {
            return [
                'valid' => false,
                'conflicts' => $conflicts,
                'warnings' => []
            ];
        }

        // Validar descanso mínimo entre turnos
        if (!$this->validateMinimumRest($operatorId, $proposedSchedule, $conflicts)) {
            return [
                'valid' => false,
                'conflicts' => $conflicts,
                'warnings' => []
            ];
        }

        // Validar horas máximas por día
        if (!$this->validateMaxHoursPerDay($operatorId, $proposedSchedule, $conflicts)) {
            return [
                'valid' => false,
                'conflicts' => $conflicts,
                'warnings' => []
            ];
        }

        // Validar horas máximas por semana
        if (!$this->validateMaxHoursPerWeek($operatorId, $proposedSchedule, $warnings)) {
            // Esto es una advertencia, no un conflicto bloqueante
        }

        // Validar descanso diario mínimo
        $this->validateDailyRest($operatorId, $proposedSchedule, $warnings);

        return [
            'valid' => count($conflicts) === 0,
            'conflicts' => $conflicts,
            'warnings' => $warnings
        ];
    }

    /**
     * Valida datos básicos del turno propuesto
     */
    private function validateBasicData(int $operatorId, array $schedule, array &$conflicts): bool
    {
        // Validar que exista el operador
        if (!isset($this->operatorAvailability[$operatorId])) {
            $conflicts[] = [
                'type' => 'OPERATOR_NOT_FOUND',
                'message' => "Operador ID {$operatorId} no registrado en el sistema",
                'severity' => 'critical'
            ];
            return false;
        }

        // Validar campos requeridos
        $requiredFields = ['start_time', 'end_time', 'vehicle_type'];
        foreach ($requiredFields as $field) {
            if (!isset($schedule[$field]) || empty($schedule[$field])) {
                $conflicts[] = [
                    'type' => 'MISSING_FIELD',
                    'message' => "Campo requerido: {$field}",
                    'severity' => 'critical'
                ];
                return false;
            }
        }

        // Validar formato de fechas
        try {
            $startTime = new DateTime($schedule['start_time']);
            $endTime = new DateTime($schedule['end_time']);

            if ($endTime <= $startTime) {
                $conflicts[] = [
                    'type' => 'INVALID_TIME_RANGE',
                    'message' => 'La hora de fin debe ser posterior a la hora de inicio',
                    'severity' => 'critical'
                ];
                return false;
            }
        } catch (Exception $e) {
            $conflicts[] = [
                'type' => 'INVALID_DATE_FORMAT',
                'message' => 'Formato de fecha inválido: ' . $e->getMessage(),
                'severity' => 'critical'
            ];
            return false;
        }

        return true;
    }

    /**
     * Valida que el operador esté autorizado para el tipo de vehículo
     */
    private function validateVehicleAuthorization(int $operatorId, array $schedule, array &$conflicts): bool
    {
        $vehicleType = $schedule['vehicle_type'];

        // Si no hay autorizaciones registradas, asumir que está autorizado
        if (!isset($this->vehicleAuthorizations[$operatorId])) {
            return true;
        }

        $authorized = $this->vehicleAuthorizations[$operatorId];

        if (!in_array($vehicleType, $authorized)) {
            $conflicts[] = [
                'type' => 'UNAUTHORIZED_VEHICLE',
                'message' => "Operador no autorizado para operar vehículo tipo: {$vehicleType}",
                'authorized_vehicles' => $authorized,
                'severity' => 'critical'
            ];
            return false;
        }

        return true;
    }

    /**
     * Valida disponibilidad del operador según su horario permitido
     */
    private function validateAvailability(int $operatorId, array $schedule, array &$conflicts): bool
    {
        if (!isset($this->operatorAvailability[$operatorId])) {
            return true;
        }

        $startTime = new DateTime($schedule['start_time']);
        $endTime = new DateTime($schedule['end_time']);
        $dayName = strtolower($startTime->format('l'));

        // Mapeo de días en inglés
        $dayMap = [
            'monday' => 'monday',
            'tuesday' => 'tuesday',
            'wednesday' => 'wednesday',
            'thursday' => 'thursday',
            'friday' => 'friday',
            'saturday' => 'saturday',
            'sunday' => 'sunday'
        ];

        $dayName = array_search($dayName, array_keys(array_flip(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])));

        $availability = $this->operatorAvailability[$operatorId];

        // Si no hay disponibilidad registrada para este día
        if (!isset($availability[$dayName])) {
            $conflicts[] = [
                'type' => 'NO_AVAILABILITY',
                'message' => "Operador no tiene disponibilidad registrada para {$dayName}",
                'severity' => 'critical'
            ];
            return false;
        }

        $availStart = DateTime::createFromFormat('H:i', $availability[$dayName]['start']);
        $availEnd = DateTime::createFromFormat('H:i', $availability[$dayName]['end']);

        // Comparar solo la hora del día
        $scheduleStart = $startTime->format('H:i');
        $scheduleEnd = $endTime->format('H:i');

        if ($scheduleStart < $availability[$dayName]['start'] || $scheduleEnd > $availability[$dayName]['end']) {
            $conflicts[] = [
                'type' => 'AVAILABILITY_MISMATCH',
                'message' => "Horario propuesto ({$scheduleStart}-{$scheduleEnd}) fuera de disponibilidad ({$availability[$dayName]['start']}-{$availability[$dayName]['end']})",
                'operator_availability' => $availability[$dayName],
                'proposed_time' => [
                    'start' => $scheduleStart,
                    'end' => $scheduleEnd
                ],
                'severity' => 'critical'
            ];
            return false;
        }

        return true;
    }

    /**
     * Valida que no haya traslape con turnos existentes
     */
    private function validateTimeOverlap(int $operatorId, array $schedule, array &$conflicts): bool
    {
        if (!isset($this->operatorSchedules[$operatorId])) {
            return true;
        }

        $proposedStart = new DateTime($schedule['start_time']);
        $proposedEnd = new DateTime($schedule['end_time']);

        foreach ($this->operatorSchedules[$operatorId] as $existingSchedule) {
            // Solo considerar turnos confirmados o pendientes
            if (isset($existingSchedule['status']) && $existingSchedule['status'] === 'cancelled') {
                continue;
            }

            $existingStart = new DateTime($existingSchedule['start_time']);
            $existingEnd = new DateTime($existingSchedule['end_time']);

            // Verificar si hay traslape
            if ($this->hasTimeOverlap($proposedStart, $proposedEnd, $existingStart, $existingEnd)) {
                $conflicts[] = [
                    'type' => 'TIME_OVERLAP',
                    'message' => 'Existe traslape con un turno ya programado',
                    'existing_schedule' => [
                        'schedule_id' => $existingSchedule['schedule_id'] ?? null,
                        'start_time' => $existingSchedule['start_time'],
                        'end_time' => $existingSchedule['end_time'],
                        'route_id' => $existingSchedule['route_id'] ?? null
                    ],
                    'severity' => 'critical'
                ];
                return false;
            }
        }

        return true;
    }

    /**
     * Valida descanso mínimo entre turnos (12 horas)
     */
    private function validateMinimumRest(int $operatorId, array $schedule, array &$conflicts): bool
    {
        if (!isset($this->operatorSchedules[$operatorId])) {
            return true;
        }

        $proposedStart = new DateTime($schedule['start_time']);
        $proposedEnd = new DateTime($schedule['end_time']);

        foreach ($this->operatorSchedules[$operatorId] as $existingSchedule) {
            if (isset($existingSchedule['status']) && $existingSchedule['status'] === 'cancelled') {
                continue;
            }

            $existingEnd = new DateTime($existingSchedule['end_time']);
            $existingStart = new DateTime($existingSchedule['start_time']);

            // Calcular descanso después del turno anterior
            if ($existingEnd < $proposedStart) {
                $restHours = ($proposedStart->getTimestamp() - $existingEnd->getTimestamp()) / 3600;
                if ($restHours < self::MIN_REST_HOURS) {
                    $conflicts[] = [
                        'type' => 'INSUFFICIENT_REST',
                        'message' => "Descanso insuficiente ({$restHours} horas). Mínimo requerido: " . self::MIN_REST_HOURS . " horas",
                        'rest_hours' => round($restHours, 2),
                        'minimum_rest' => self::MIN_REST_HOURS,
                        'previous_schedule' => [
                            'end_time' => $existingSchedule['end_time'],
                            'schedule_id' => $existingSchedule['schedule_id'] ?? null
                        ],
                        'severity' => 'critical'
                    ];
                    return false;
                }
            }

            // Calcular descanso antes del próximo turno
            if ($proposedEnd < $existingStart) {
                $restHours = ($existingStart->getTimestamp() - $proposedEnd->getTimestamp()) / 3600;
                if ($restHours < self::MIN_REST_HOURS) {
                    $conflicts[] = [
                        'type' => 'INSUFFICIENT_REST_AFTER',
                        'message' => "Descanso insuficiente ({$restHours} horas) antes del siguiente turno. Mínimo requerido: " . self::MIN_REST_HOURS . " horas",
                        'rest_hours' => round($restHours, 2),
                        'minimum_rest' => self::MIN_REST_HOURS,
                        'next_schedule' => [
                            'start_time' => $existingSchedule['start_time'],
                            'schedule_id' => $existingSchedule['schedule_id'] ?? null
                        ],
                        'severity' => 'critical'
                    ];
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Valida que no se excedan las horas máximas por día (8 horas)
     */
    private function validateMaxHoursPerDay(int $operatorId, array $schedule, array &$conflicts): bool
    {
        if (!isset($this->operatorSchedules[$operatorId])) {
            return true;
        }

        $proposedStart = new DateTime($schedule['start_time']);
        $proposedEnd = new DateTime($schedule['end_time']);
        $proposedDate = $proposedStart->format('Y-m-d');

        // Calcular horas del turno propuesto
        $proposedHours = ($proposedEnd->getTimestamp() - $proposedStart->getTimestamp()) / 3600;

        // Sumar horas de otros turnos en el mismo día
        $totalHours = $proposedHours;

        foreach ($this->operatorSchedules[$operatorId] as $existingSchedule) {
            if (isset($existingSchedule['status']) && $existingSchedule['status'] === 'cancelled') {
                continue;
            }

            $existingStart = new DateTime($existingSchedule['start_time']);
            $existingDate = $existingStart->format('Y-m-d');

            if ($existingDate === $proposedDate) {
                $existingEnd = new DateTime($existingSchedule['end_time']);
                $existingHours = ($existingEnd->getTimestamp() - $existingStart->getTimestamp()) / 3600;
                $totalHours += $existingHours;
            }
        }

        if ($totalHours > self::MAX_HOURS_PER_DAY) {
            $conflicts[] = [
                'type' => 'EXCEEDED_DAILY_HOURS',
                'message' => "Horas totales del día ({$totalHours} horas) exceden el máximo permitido: " . self::MAX_HOURS_PER_DAY . " horas",
                'total_hours' => round($totalHours, 2),
                'maximum_hours' => self::MAX_HOURS_PER_DAY,
                'date' => $proposedDate,
                'severity' => 'critical'
            ];
            return false;
        }

        return true;
    }

    /**
     * Valida que no se excedan las horas máximas por semana (48 horas)
     * Este es un warning, no bloquea la asignación
     */
    private function validateMaxHoursPerWeek(int $operatorId, array $schedule, array &$warnings): bool
    {
        if (!isset($this->operatorSchedules[$operatorId])) {
            return true;
        }

        $proposedStart = new DateTime($schedule['start_time']);
        $proposedEnd = new DateTime($schedule['end_time']);
        $weekStart = (clone $proposedStart)->modify('Monday this week');
        $weekEnd = (clone $weekStart)->modify('Sunday this week')->setTime(23, 59, 59);

        // Calcular horas del turno propuesto
        $proposedHours = ($proposedEnd->getTimestamp() - $proposedStart->getTimestamp()) / 3600;
        $totalHours = $proposedHours;

        foreach ($this->operatorSchedules[$operatorId] as $existingSchedule) {
            if (isset($existingSchedule['status']) && $existingSchedule['status'] === 'cancelled') {
                continue;
            }

            $existingStart = new DateTime($existingSchedule['start_time']);

            if ($existingStart >= $weekStart && $existingStart <= $weekEnd) {
                $existingEnd = new DateTime($existingSchedule['end_time']);
                $existingHours = ($existingEnd->getTimestamp() - $existingStart->getTimestamp()) / 3600;
                $totalHours += $existingHours;
            }
        }

        if ($totalHours > self::MAX_HOURS_PER_WEEK) {
            $warnings[] = [
                'type' => 'EXCEEDED_WEEKLY_HOURS',
                'message' => "Horas totales de la semana ({$totalHours} horas) exceden lo recomendado: " . self::MAX_HOURS_PER_WEEK . " horas",
                'total_hours' => round($totalHours, 2),
                'recommended_hours' => self::MAX_HOURS_PER_WEEK,
                'week_start' => $weekStart->format('Y-m-d'),
                'severity' => 'warning'
            ];
            return false;
        }

        return true;
    }

    /**
     * Valida descanso diario mínimo (8 horas entre fin de turno y inicio del siguiente)
     */
    private function validateDailyRest(int $operatorId, array $schedule, array &$warnings): void
    {
        if (!isset($this->operatorSchedules[$operatorId])) {
            return;
        }

        $proposedStart = new DateTime($schedule['start_time']);
        $proposedEnd = new DateTime($schedule['end_time']);
        $proposedDate = $proposedStart->format('Y-m-d');

        foreach ($this->operatorSchedules[$operatorId] as $existingSchedule) {
            if (isset($existingSchedule['status']) && $existingSchedule['status'] === 'cancelled') {
                continue;
            }

            $existingStart = new DateTime($existingSchedule['start_time']);
            $existingEnd = new DateTime($existingSchedule['end_time']);
            $existingDate = $existingStart->format('Y-m-d');

            // Verificar descanso del día anterior
            $previousDate = (clone $proposedStart)->modify('-1 day')->format('Y-m-d');
            if ($existingDate === $previousDate) {
                $restHours = ($proposedStart->getTimestamp() - $existingEnd->getTimestamp()) / 3600;
                if ($restHours < self::MIN_REST_DAILY) {
                    $warnings[] = [
                        'type' => 'INSUFFICIENT_DAILY_REST',
                        'message' => "Descanso diario insuficiente ({$restHours} horas). Recomendado: " . self::MIN_REST_DAILY . " horas",
                        'rest_hours' => round($restHours, 2),
                        'recommended_rest' => self::MIN_REST_DAILY,
                        'severity' => 'warning'
                    ];
                }
            }
        }
    }

    /**
     * Verifica si hay traslape entre dos períodos de tiempo
     */
    private function hasTimeOverlap(DateTime $start1, DateTime $end1, DateTime $start2, DateTime $end2): bool
    {
        return !($end1 <= $start2 || $end2 <= $start1);
    }

    /**
     * Obtiene todos los turnos de un operador
     */
    public function getOperatorSchedules(int $operatorId): array
    {
        return $this->operatorSchedules[$operatorId] ?? [];
    }

    /**
     * Limpia los datos almacenados
     */
    public function reset(): void
    {
        $this->operatorSchedules = [];
        $this->vehicleAuthorizations = [];
        $this->operatorAvailability = [];
        $this->validationErrors = [];
    }

    /**
     * Obtiene estadísticas de carga del operador
     */
    public function getOperatorStats(int $operatorId, string $dateStart, string $dateEnd): array
    {
        if (!isset($this->operatorSchedules[$operatorId])) {
            return [];
        }

        $start = new DateTime($dateStart);
        $end = new DateTime($dateEnd);
        $totalHours = 0;
        $scheduleCount = 0;
        $schedulesByDay = [];

        foreach ($this->operatorSchedules[$operatorId] as $schedule) {
            if (isset($schedule['status']) && $schedule['status'] === 'cancelled') {
                continue;
            }

            $scheduleStart = new DateTime($schedule['start_time']);
            $scheduleEnd = new DateTime($schedule['end_time']);

            if ($scheduleStart >= $start && $scheduleStart <= $end) {
                $hours = ($scheduleEnd->getTimestamp() - $scheduleStart->getTimestamp()) / 3600;
                $totalHours += $hours;
                $scheduleCount++;

                $day = $scheduleStart->format('Y-m-d');
                if (!isset($schedulesByDay[$day])) {
                    $schedulesByDay[$day] = ['count' => 0, 'hours' => 0];
                }
                $schedulesByDay[$day]['count']++;
                $schedulesByDay[$day]['hours'] += $hours;
            }
        }

        return [
            'total_hours' => round($totalHours, 2),
            'total_schedules' => $scheduleCount,
            'average_hours_per_day' => $scheduleCount > 0 ? round($totalHours / $scheduleCount, 2) : 0,
            'schedules_by_day' => $schedulesByDay,
            'period' => [
                'start' => $dateStart,
                'end' => $dateEnd
            ]
        ];
    }
}
