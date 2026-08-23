-- Optional preview data for a freshly imported, otherwise empty LGC database.
-- Do not import this file on the production site.

START TRANSACTION;

INSERT INTO players (name,nickname,player_number,batting_style,bowling_style,nationality_code,bio,joined_date,active) VALUES
('Muhammad Rahman','Mushi','07','Right-handed','Right-arm medium','BD','Calm at the crease and fiercely competitive when the monthly table gets close.','2026-04-12',1),
('Rahim Ahmed','Rahi','18','Right-handed','Right-arm fast','BD','An attacking bowler who can change a session in a single spell.','2026-04-12',1),
('Imran Hossain','Imu','23','Left-handed','Right-arm off break','BD','A fearless all-rounder with quick hands in the field.','2026-04-19',1),
('Hasan Mahmud','Hasu','11','Right-handed','Right-arm medium','BD','Reliable runs, sharp catches and plenty of competitive energy.','2026-05-03',1),
('Shakib Islam','Shak','75','Left-handed','Left-arm orthodox','BD','A patient batter who always finds a way into the contest.','2026-05-03',1),
('Tanvir Alam','Tani','33','Right-handed','Right-arm leg break','BD','Unpredictable spin and an even better sense of humour.','2026-05-17',1);

SET @muhammad = (SELECT id FROM players WHERE name='Muhammad Rahman' ORDER BY id DESC LIMIT 1);
SET @rahim = (SELECT id FROM players WHERE name='Rahim Ahmed' ORDER BY id DESC LIMIT 1);
SET @imran = (SELECT id FROM players WHERE name='Imran Hossain' ORDER BY id DESC LIMIT 1);
SET @hasan = (SELECT id FROM players WHERE name='Hasan Mahmud' ORDER BY id DESC LIMIT 1);
SET @shakib = (SELECT id FROM players WHERE name='Shakib Islam' ORDER BY id DESC LIMIT 1);
SET @tanvir = (SELECT id FROM players WHERE name='Tanvir Alam' ORDER BY id DESC LIMIT 1);

INSERT INTO sessions (session_date,location,title,notes,status,player_of_day_id) VALUES ('2026-07-26','Grünau, Leipzig','Golden hour cricket','A close finish under the evening sun.','completed',@rahim);
SET @s1 = LAST_INSERT_ID();
INSERT INTO session_players (session_id,player_id,runs,balls_faced,fours,sixes,is_out,wickets,balls_bowled,runs_conceded,catches,run_outs,stumpings,bonus_points,penalty_points,excluded,calculated_points) VALUES
(@s1,@muhammad,34,26,4,1,1,1,18,16,1,0,0,0,0,0,65),
(@s1,@rahim,25,20,3,1,1,2,24,19,0,0,0,0,0,0,70),
(@s1,@imran,18,15,2,0,1,3,24,21,1,0,0,0,0,0,80),
(@s1,@hasan,12,14,1,0,1,1,12,12,1,0,0,0,0,0,33),
(@s1,@shakib,8,12,1,0,1,0,6,8,1,0,0,0,0,0,14);

INSERT INTO sessions (session_date,location,title,notes,status,player_of_day_id) VALUES ('2026-08-02','Grünau, Leipzig','August opener','Fast outfield, big shots and a fresh monthly race.','completed',@imran);
SET @s2 = LAST_INSERT_ID();
INSERT INTO session_players (session_id,player_id,runs,balls_faced,fours,sixes,is_out,wickets,balls_bowled,runs_conceded,catches,run_outs,stumpings,bonus_points,penalty_points,excluded,calculated_points) VALUES
(@s2,@muhammad,31,24,3,1,1,2,18,17,0,0,0,0,0,0,71),
(@s2,@rahim,28,19,4,1,1,1,18,22,1,0,0,0,0,0,54),
(@s2,@imran,42,28,5,2,1,2,24,23,1,0,0,0,0,0,101),
(@s2,@hasan,19,17,2,0,1,1,12,15,0,1,0,0,0,0,43),
(@s2,@tanvir,11,13,1,0,1,2,18,18,1,0,0,0,0,0,47);

INSERT INTO sessions (session_date,location,title,notes,status,player_of_day_id) VALUES ('2026-08-09','Grünau, Leipzig','Sunday showdown','The bowlers owned the middle of the session.','completed',@rahim);
SET @s3 = LAST_INSERT_ID();
INSERT INTO session_players (session_id,player_id,runs,balls_faced,fours,sixes,is_out,wickets,balls_bowled,runs_conceded,catches,run_outs,stumpings,bonus_points,penalty_points,excluded,calculated_points) VALUES
(@s3,@muhammad,37,29,4,2,1,1,18,18,1,0,0,0,0,0,70),
(@s3,@rahim,21,18,2,1,1,3,24,17,1,0,0,0,0,0,95),
(@s3,@imran,27,23,3,0,1,1,12,15,0,0,0,0,0,0,45),
(@s3,@hasan,30,25,3,1,1,2,18,16,1,0,0,0,0,0,75),
(@s3,@shakib,16,18,2,0,1,0,12,17,2,0,0,0,0,0,28);

INSERT INTO sessions (session_date,location,title,notes,status,player_of_day_id) VALUES ('2026-08-16','Grünau, Leipzig','Six-hitting evening','A lively session with the monthly lead changing hands.','completed',@muhammad);
SET @s4 = LAST_INSERT_ID();
INSERT INTO session_players (session_id,player_id,runs,balls_faced,fours,sixes,is_out,wickets,balls_bowled,runs_conceded,catches,run_outs,stumpings,bonus_points,penalty_points,excluded,calculated_points) VALUES
(@s4,@muhammad,46,31,5,3,1,2,24,21,1,0,0,0,0,0,107),
(@s4,@rahim,33,25,4,1,1,2,18,20,0,0,0,0,0,0,74),
(@s4,@imran,28,22,3,2,1,1,18,24,1,0,0,0,0,0,55),
(@s4,@hasan,22,20,2,1,1,1,12,16,1,0,0,0,0,0,46),
(@s4,@tanvir,14,15,1,1,1,0,12,19,0,1,0,0,0,1,0);

COMMIT;
