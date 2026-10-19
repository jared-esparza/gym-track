-- Back up first. Run ONCE before deploying the new API. MySQL DDL auto-commits.
ALTER TABLE exercises MODIFY muscle_group_id TINYINT UNSIGNED NULL,
  DROP INDEX uq_exercises_user_group_name,
  ADD COLUMN muscle_group_key TINYINT UNSIGNED GENERATED ALWAYS AS (COALESCE(muscle_group_id, 0)) STORED,
  ADD UNIQUE KEY uq_exercises_user_group_name (user_id, muscle_group_key, name);
ALTER TABLE records DROP FOREIGN KEY fk_records_workout;
ALTER TABLE records MODIFY workout_id INT UNSIGNED NULL,
  ADD CONSTRAINT fk_records_workout FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE SET NULL;
CREATE TABLE workout_exercises (
  workout_id INT UNSIGNED NOT NULL,
  exercise_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (workout_id, exercise_id),
  CONSTRAINT fk_we_workout FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE,
  CONSTRAINT fk_we_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO workout_exercises (workout_id, exercise_id)
SELECT DISTINCT w.id, e.id FROM workouts w
JOIN workout_muscle_groups wmg ON wmg.workout_id = w.id
JOIN exercises e ON e.muscle_group_id = wmg.muscle_group_id AND e.user_id = w.user_id;
-- workout_muscle_groups is retained only as legacy data; the application no longer reads it.
