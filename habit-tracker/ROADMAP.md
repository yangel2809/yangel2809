# Roadmap

Fuera del MVP a propósito. Anotado para decidir después, no para construir ahora.

## Candidatos con buen retorno

- **Pausar un hábito (vacaciones, enfermedad).** Hoy, archivar y restaurar
  hace que los días archivados cuenten como fallo (solo se excluye lo anterior
  a `start_date`). Una tabla `habit_pauses (habit_id, from, to)` que
  `HabitStatsService` excluya resolvería esto sin trucos.
- **Reordenar hábitos.** `sort_order` ya existe en la tabla; falta la UI
  (arrastrar o botones subir/bajar).
- **Recordatorio nocturno para planear mañana.** Sin push (fuera de alcance):
  la alternativa barata es un correo diario, pero el plan gratis de
  InfinityFree no envía correo. Requiere otro hosting o un servicio externo.
- **Marcar días más antiguos.** Hoy se puede registrar hasta 7 días atrás
  (`LocalDate::EDITABLE_DAYS`). Si hace falta más, basta con subir la
  constante.

## Fuera de alcance explícito (MVP)

- Notificaciones push
- Gamificación (puntos, insignias)
- Múltiples usuarios
- Integraciones con otras apps
- App nativa
- Modo oscuro

## Técnico

- **Migrar a Laravel 12** cuando el hosting lo permita (también requiere PHP 8.2).
  Laravel 11 ya no recibe parches de seguridad; ver avisos de `composer audit`.
- **Si se deja InfinityFree** (VPS u hosting con SSH): usar la estructura
  estándar con `public/` como document root, `php artisan migrate` y
  `config:cache` / `route:cache`. `deploy:build` deja de ser necesario.
