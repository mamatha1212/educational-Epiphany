import openpyxl
import re

excel_path = r"C:\Users\91767\Downloads\AP SCHOOLS LIST GOVT & AIDED.xlsx"
wb = openpyxl.load_workbook(excel_path, read_only=True)
sheet = wb.active

dist_mandals = {}
total_valid_schools = 0

for row in sheet.iter_rows(values_only=True):
    if not row or row[0] == 'S.No.':
        continue
    dist_raw = str(row[1] or '').strip()
    mandal_raw = str(row[2] or '').strip()
    code_raw = str(row[3] or '').strip()
    school_raw = str(row[4] or '').strip()
    
    if dist_raw.upper() in ('TOTAL', ''):
        continue
    
    # Clean code: if ends with .0
    if code_raw.endswith('.0'):
        code_raw = code_raw[:-2]
    
    # Clean 11 digit check
    if not (code_raw.isdigit() and len(code_raw) == 11):
        continue
        
    total_valid_schools += 1
    dist_upper = dist_raw.upper()
    # Normalize some common variations
    if dist_upper == 'MARKAPUR':
        dist_upper = 'MARKAPURAM'
        
    mandal_upper = mandal_raw.upper()
    
    if dist_upper not in dist_mandals:
        dist_mandals[dist_upper] = {}
    if mandal_upper not in dist_mandals[dist_upper]:
        dist_mandals[dist_upper][mandal_upper] = 0
    dist_mandals[dist_upper][mandal_upper] += 1

print(f"Total valid schools with 11-digit code: {total_valid_schools}")
print(f"Total distinct normalized districts: {len(dist_mandals)}")
for d in sorted(dist_mandals.keys()):
    m_count = len(dist_mandals[d])
    s_count = sum(dist_mandals[d].values())
    print(f"  {d}: {m_count} mandals, {s_count} schools")
