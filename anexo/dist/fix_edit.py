
import os

file_path = r'c:\Users\luizb\semth\semas\dist\editarSolicitante.php'

with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Find the split point (start of duplicate content)
# We know it starts at line 1856 (0-indexed: 1855) exactly.
# But let's look for "<!-- jsPDF para gerar PDF no cliente -->" appearing a second time.
count = 0
split_index = -1
for i, line in enumerate(lines):
    if "<!-- jsPDF para gerar PDF no cliente -->" in line:
        count += 1
        if count == 2:
            split_index = i
            break

if split_index != -1:
    print(f"Found duplicate content at line {split_index + 1}. Truncating...")
    lines = lines[:split_index]
else:
    print("Duplicate content not found by marker. Checking line count.")
    # If not found, maybe I miscounted or it was edited.
    # We want to end with </html>. Use that as anchor.
    # Find the FIRST </html>.
    for i, line in enumerate(lines):
        if "</html>" in line:
            # We want to keep this line, but discard everything after.
            # But the duplicate content is AFTER this.
            # Let's check if there is content after.
            if i < len(lines) - 10: # arbitrary margin
                 # confirm duplicate
                 print(f"Found </html> at {i+1}, and file has {len(lines)} lines. Truncating.")
                 lines = lines[:i+1]
                 break

# Now inject the mask trigger script.
# We want to put it before the closing of the LAST script block.
# The last script block ends at line 1852 (in original file).
# It closes the IIFE for scanner: `})();` then `</script>`.
# Let's look for `</script>` from the end.
insert_index = -1
for i in range(len(lines)-1, -1, -1):
    if "</script>" in lines[i]:
        insert_index = i
        break

mask_script = """
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const event = new Event('input');
                const cpf = document.getElementById('cpf');
                const conjCpf = document.getElementById('conj_cpf');
                const tel = document.getElementById('telefone');

                if(cpf && cpf.value) cpf.dispatchEvent(event);
                if(conjCpf && conjCpf.value) conjCpf.dispatchEvent(event);
                if(tel && tel.value) tel.dispatchEvent(event);
                document.querySelectorAll('.moeda').forEach(el => {
                    if(el.value) el.dispatchEvent(event);
                });
            }, 500);
        });
    </script>
"""

if insert_index != -1:
    # Insert BEFORE </body> (lines[1853]) is safer/cleaner than inside another script tag.
    # Let's find </body>
    body_end_index = -1
    for i in range(len(lines)-1, -1, -1):
        if "</body>" in lines[i]:
            body_end_index = i
            break
    
    if body_end_index != -1:
        lines.insert(body_end_index, mask_script)
    else:
        lines.append(mask_script)
else:
    lines.append(mask_script)

with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(lines)

print("File fixed.")
