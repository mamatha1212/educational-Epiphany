import sqlite3
import re

db_path = r"c:\Users\91767\Desktop\eemt\eemt2027_app\database\eemt2027.sqlite"
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

cursor.execute("SELECT DISTINCT district, mandal FROM schools_master")
db_pairs = cursor.fetchall()
print(f"Total distinct (district, mandal) in DB: {len(db_pairs)}")

# Build lookup by (cleaned_district, cleaned_mandal) -> (db_district, db_mandal)
db_dist_lookup = {}
db_mandal_lookup = {}

for d, m in db_pairs:
    d_clean = re.sub(r'-\d+$', '', d).strip().upper()
    m_clean = re.sub(r'-\d+$', '', m).strip().upper()
    
    if d_clean not in db_dist_lookup:
        db_dist_lookup[d_clean] = d
    db_mandal_lookup[(d_clean, m_clean)] = (d, m)

print(f"Distinct cleaned districts in DB: {len(db_dist_lookup)}")
print(f"Distinct cleaned (dist, mandal) pairs in DB: {len(db_mandal_lookup)}")

conn.close()
