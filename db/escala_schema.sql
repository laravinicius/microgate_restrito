-- Schema para tabela de escalas
CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  shift VARCHAR(50) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (date),
  CONSTRAINT fk_schedule_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exemplos de inserts usando usernames já cadastrados
-- Datas exemplo: 2026-02-20 (fev), 2026-03-05 (mar), 2026-04-01 (abr)
INSERT INTO schedules (user_id, date, shift, note)
SELECT id, '2026-02-20', 'MANHÃ', 'Escala inicial' FROM users WHERE username = 'lucas';
INSERT INTO schedules (user_id, date, shift, note)
SELECT id, '2026-02-20', 'TARDE', 'Escala inicial' FROM users WHERE username = 'ronaldo';
INSERT INTO schedules (user_id, date, shift, note)
SELECT id, '2026-02-20', 'NOITE', 'Escala inicial' FROM users WHERE username = 'jefferson';

INSERT INTO schedules (user_id, date, shift, note)
SELECT id, '2026-03-05', 'MANHÃ', 'Escala mês +1' FROM users WHERE username = 'paulo.h';
INSERT INTO schedules (user_id, date, shift, note)
SELECT id, '2026-03-05', 'TARDE', 'Escala mês +1' FROM users WHERE username = 'joao';

INSERT INTO schedules (user_id, date, shift, note)
SELECT id, '2026-04-01', 'MANHÃ', 'Escala mês +2' FROM users WHERE username = 'paulo.j';
INSERT INTO schedules (user_id, date, shift, note)
SELECT id, '2026-04-01', 'TARDE', 'Escala mês +2' FROM users WHERE username = 'igor';

-- Você pode adicionar mais entradas substituindo os usernames conforme sua planilha
