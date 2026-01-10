
import sys

def check_balance(filename):
    stack = []
    line_no = 0
    with open(filename, 'r') as f:
        for line in f:
            line_no += 1
            # Simple parser: ignores strings/comments for speed, assuming error is in structure
            # But better to handle strings slightly or just ignore known safe lines
            
            # Very basic: just count { and }
            for char in line:
                if char == '{':
                    stack.append(line_no)
                elif char == '}':
                    if not stack:
                        print(f"Error: Unexpected }} at line {line_no}")
                        return
                    stack.pop()

    if stack:
        print(f"Error: {len(stack)} Unclosed {{. Last one opened at line {stack[-1]}")
        # Print top 5
        if len(stack) > 1:
             print(f"Previous open braces at: {stack[-5:]}")

check_balance('/Volumes/WORKER/machines/x32-controller/client/src/App.jsx')
