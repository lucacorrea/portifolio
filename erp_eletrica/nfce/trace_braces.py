
import sys

def audit_braces(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()

    # Simple state machine to ignore strings and comments
    i = 0
    stack = []
    line_num = 1
    in_string = None # ' or "
    in_comment = None # // or /*
    
    while i < len(content):
        char = content[i]
        
        if char == '\n':
            line_num += 1
            if in_comment == '//':
                in_comment = None
        
        if in_comment:
            if in_comment == '/*' and content[i:i+2] == '*/':
                in_comment = None
                i += 1
        elif in_string:
            if char == in_string and content[i-1] != '\\':
                in_string = None
        else:
            if content[i:i+2] == '//':
                in_comment = '//'
                i += 1
            elif content[i:i+2] == '/*':
                in_comment = '/*'
                i += 1
            elif char in ["'", '"']:
                in_string = char
            elif char == '{':
                # Get some context
                context = content[max(0, i-20):i].replace('\n', ' ')
                stack.append((line_num, context))
            elif char == '}':
                if not stack:
                    print(f"EXTRA CLOSING BRACE at line {line_num}")
                    # Print context
                    print(f"Context: {content[max(0, i-40):i+40].replace('\n', ' ')}")
                else:
                    stack.pop()
        i += 1
    
    if stack:
        print("UNCLOSED BRACES:")
        for line, context in stack:
            print(f"Line {line}: ...{context} {{")

if __name__ == "__main__":
    audit_braces(sys.argv[1])
