import openpyxl
import sqlite3
import re

excel_path = r"C:\Users\91767\Downloads\AP SCHOOLS LIST GOVT & AIDED.xlsx"
db_path = r"c:\Users\91767\Desktop\eemt\eemt2027_app\database\eemt2027.sqlite"

wb = openpyxl.load_workbook(excel_path, read_only=True)
sheet = wb.active

excel_schools = []
for row in sheet.iter_rows(values_only=True):
    if not row or row[0] == 'S.No.':
        continue
    code = str(row[3] or '').strip()
    if code.endswith('.0'):
        code = code[:-2]
    excel_schools.append({
        'dist': str(row[1] or '').strip(),
        'mandal': str(row[2] or '').strip(),
        'code': code,
        'name': str(row[4] or '').strip(),
        'mgmt': str(row[5] or '').strip(),
        'type': str(row[6] or '').strip()
    })

print(f"Total rows in Excel: {len(excel_schools)}")

# Check distinct managements in Excel
mgmts = set(s['mgmt'] for s in excel_schools)
print(f"Managements ({len(mgmts)}):")
for m in sorted(mgmts):
    print(" ", m)

# Connect DB
conn = sqlite3.connect(db_path)
cursor = conn.cursor()
cursor.execute("SELECT id, district, mandal, school_name, unique_code FROM schools_master WHERE school_name NOT LIKE 'Other %'")
db_real_schools = cursor.fetchall()
print(f"\nReal DB schools (excluding 'Other ...'): {len(db_real_schools)}")
conn.close()
