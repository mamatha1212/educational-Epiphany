import openpyxl
import sqlite3
import re

excel_path = r"C:\Users\91767\Downloads\AP SCHOOLS LIST GOVT & AIDED.xlsx"
db_path = r"c:\Users\91767\Desktop\eemt\eemt2027_app\database\eemt2027.sqlite"

wb = openpyxl.load_workbook(excel_path, read_only=True)
sheet = wb.active

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Get sample schools_master records
cursor.execute("SELECT id, district, mandal, school_name, unique_code FROM schools_master LIMIT 20")
sample_db = cursor.fetchall()
print("Sample schools_master:")
for r in sample_db[:5]:
    print(" ", r)

cursor.execute("SELECT COUNT(*) FROM schools_master")
db_count = cursor.fetchone()[0]
print(f"\nTotal in schools_master: {db_count}")

# Check how many unique_code in schools_master are already 11-digit numbers vs AP-...
cursor.execute("SELECT COUNT(*) FROM schools_master WHERE unique_code GLOB '[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]'")
digit_codes = cursor.fetchone()[0]
print(f"Schools with 11-digit UDISE code in DB: {digit_codes} / {db_count}")

conn.close()
