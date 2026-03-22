import re

def process_style_css():
    with open('c:/Users/User/Documents/portifolio/temp3_utf8.css', 'r', encoding='utf-8') as f:
        content = f.read()

    # Change root colors
    content = re.sub(r'--primary-color:\s*#[0-9a-fA-F]+;', '--primary-color: #000000;', content)
    content = re.sub(r'--primary-hover:\s*#[0-9a-fA-F]+;', '--primary-hover: #333333;', content)
    content = re.sub(r'--secondary-color:\s*#[0-9a-fA-F]+;', '--secondary-color: #141414;', content)

    # Force sidebar to keep yellow active states as it was
    content = re.sub(
        r'\.sidebar-menu li\.active a i \{[\s\n]*color:[^;]+;',
        '.sidebar-menu li.active a i {\n    color: #FFC107;',
        content
    )
    content = re.sub(
        r'\.sidebar-menu li:hover a,[\s\n]*\.sidebar-menu li\.active a \{[\s\n]*background:[^;]+;[\s\n]*color:[^;]+;[\s\n]*border-left-color:[^;]+;',
        '.sidebar-menu li:hover a,\n.sidebar-menu li.active a {\n    background: rgba(255, 255, 255, 0.05);\n    color: white;\n    border-left-color: #FFC107;',
        content
    )
    
    # Also fix sidebar header logo if it had color
    content = re.sub(r'\.sidebar-header i \{[\s\n]*font-size:[^;]+;[\s\n]*color:[^;]+;', '.sidebar-header i {\n    font-size: 1.8rem;\n    color: #FFC107;', content)

    with open('c:/Users/User/Documents/portifolio/erp_eletrica/style.css', 'w', encoding='utf-8') as f:
        f.write(content)

def process_corporate_css():
    with open('c:/Users/User/Documents/portifolio/temp4_utf8.css', 'r', encoding='utf-8') as f:
        content = f.read()

    # Change root colors
    content = re.sub(r'--erp-primary:\s*#[0-9a-fA-F]+;', '--erp-primary: #000000;', content)
    content = re.sub(r'--erp-primary-hover:\s*#[0-9a-fA-F]+;', '--erp-primary-hover: #333333;', content)
    content = re.sub(r'--erp-secondary:\s*#[0-9a-fA-F]+;', '--erp-secondary: #141414;', content)
    
    # Change bootstrap overrides
    content = re.sub(r'--bs-primary:\s*#[0-9a-fA-F]+;', '--bs-primary: #000000;', content)
    content = re.sub(r'--bs-primary-rgb:\s*[0-9,\s]+;', '--bs-primary-rgb: 0, 0, 0;', content)
    content = re.sub(r'--bs-link-color:\s*#[0-9a-fA-F]+;', '--bs-link-color: #000000;', content)
    content = re.sub(r'--bs-link-hover-color:\s*#[0-9a-fA-F]+;', '--bs-link-hover-color: #333333;', content)

    # Change sidebar overlay
    content = re.sub(
        r'\.sidebar \{[\s\n]*width: 260px;[\s\n]*background: #[0-9a-fA-F]+;',
        '.sidebar {\n    width: 260px;\n    background: var(--erp-secondary);',
        content
    )

    with open('c:/Users/User/Documents/portifolio/erp_eletrica/public/css/corporate.css', 'w', encoding='utf-8') as f:
        f.write(content)

process_style_css()
process_corporate_css()
print("Done")
