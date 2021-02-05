## JIT Analysis 2

The PHP code in the direct call case was:

```php

function scale(int $i) : float {
    return 0.001 * $i;
}

function accumulate(int $iMax) : float {
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += scale($i);
    }
    return $fAcum;
}
```

The generated opcode for this was:

```
function name:  scale
number of ops:  3
compiled vars:  !0 = $i
line      #* E I O op                           fetch          ext  return  operands
-------------------------------------------------------------------------------------
    8     0  E >   RECV                                             !0      
    9     1        MUL                                              ~1      !0, 0.001
          2      > RETURN                                                   ~1

function name:  accumulate
number of ops:  12
compiled vars:  !0 = $iMax, !1 = $fAcum, !2 = $i
line      #* E I O op                           fetch          ext  return  operands
-------------------------------------------------------------------------------------
   15     0  E >   RECV                                             !0      
   16     1        QM_ASSIGN                                        !1      0
   18     2        QM_ASSIGN                                        !2      1
          3      > JMP                                                      ->9
   19     4    >   INIT_FCALL                                               'scale'
          5        SEND_VAR                                                 !2
          6        DO_FCALL                                      0  $3      
          7        ADD                                              !1      !1, $3
   18     8        PRE_INC                                                  !2
          9    >   IS_SMALLER                                               !2, !0
         10      > JMPNZ                                                    ~3, ->4
   21    11    > > RETURN                                                   !1
```

This is requires two more opcodes than the inline version in order to set up the function call to `scale()`, which in turn is 3 opcodes. The performance hit of this small refactoring is large. To recap:

| Operation | Strict? | JIT Disabled | JIT Enabled | Increase with JIT | Call Overhead | Call Overhead (JIT) |
| --------- | ------- | ------------ | ----------- | - | - | - |
| inline    | No      | 11.87        | 2.94        | 4.04 | _N/A_ | _N/A_ |
| direct    | No      | 34.26        | 12.91       | 2.65 | 2.89 | 4.39 |

It is clear the function call overhead hits both interpretive and JIT execution modes hard, but is particularly bad for the JIT path. The JIT generated the following for the above opcode:

```asm

TRACE-1$accumulate$18: 
    mov     $EG(jit_trace_num), %rax ; Preamble
    mov     $0x1, (%rax)
    mov     $EG(vm_stack_end), %rax
    mov     (%rax), %rcx
    mov     $EG(vm_stack_top), %rax
    sub     (%rax), %rcx
    cmp     $0x70, %rcx
    jb      jit$$trace_exit_0
    cmp     $0x5, 0x68(%r14)
	jnz     jit$$trace_exit_1
	
    ; %r14 contains call frame data

.L1:
    ; This is our loop head.
    mov     0x70(%r14), %rax     ; Looks like $i doesn't get assigned to a register this time. It lives at offset 0x70 on the stack
    cmp     0x50(%r14), %rax     ; Looks like $iMax doesnt get assigned to a register this time. It lives at offset 0x50 on the stack
    jge     jit$$trace_exit_2    ; Loop exit condition here.

    ; This appears to be some sort of initialise once test (See .L4)
    mov     0x40(%r14), %rax
    mov     0x8(%rax), %rax
    test    %rax, %rax
    jz      .L4

.L2:
    ; This looks like some PHP call stack instrumentation.
    ; It could be stack state preservation in case an exception is raised when calling
    ; the scale() operation (see .L5)
    
    mov     $EG(vm_stack_top), %r15
    mov     (%r15), %r15
    mov     $EG(vm_stack_top), %rdx
    add     $0x70, (%rdx)
    mov     $0x0, 0x28(%r15)
    mov     %rax, 0x18(%r15)
    mov     $0x0, 0x20(%r15)
    mov     $0x1, 0x2c(%r15)
    mov     0x70(%r14), %rdx
    mov     %rdx, 0x50(%r15)
    mov     $0x4, 0x58(%r15)
    mov     $0x7fd38c094b38, %rax
    mov     %rax, (%r14)
    mov     %r14, 0x30(%r15)
    mov     $0x0, 0x8(%r15)
    lea     0x80(%r14), %rdx
    mov     %rdx, 0x10(%r15)
    mov     0x18(%r15), %rax
    mov     0x50(%rax), %rdx
    mov     $0x55795bbcbc38, %rcx
    add     (%rcx), %rdx
    mov     (%rdx), %rdx
    mov     %rdx, 0x40(%r15)
    mov     $EG(current_execute_data), %rcx
    mov     %r15, (%rcx)
    mov     %r15, %r14
    cmp     $0x1, 0x2c(%r14)
    jb      jit$$trace_exit_3
    cmp     $0x4, 0x58(%r14)
    jnz     .L5
    
.L3:
    ; This looks like our multiply-accumulation code seen before
    vxorps      %xmm0, %xmm0, %xmm0          ; Clear out %xmm0
    vcvtsi2sd   0x50(%r14), %xmm0, %xmm0     ; Cast $i to double in %xmm0
    mov         $0x7fd38c094828, %rax        ; Load up the literal 0.001 address
    vmulsd      (%rax), %xmm0, %xmm0         ; Indirect access to literal

    ; Put return value on the stack
    mov         0x10(%r14), %rcx             ; Calculate return address
    vmovsd      %xmm0, (%rcx)                ; Save return

    ; Stack management
    mov         $0x5, 0x8(%rcx)
    mov         $EG(vm_stack_top), %rax
    mov         %r14, (%rax)

    ; Restore the stack pointer
    mov         0x30(%r14), %r14
    mov         $EG(current_execute_data), %rax
    mov         %r14, (%rax)
    
    vmovsd      0x60(%r14), %xmm0            ; Load the return value into %xmm0
    vaddsd      0x80(%r14), %xmm0, %xmm0     ; Add the accumulator
    vmovsd      %xmm0, 0x60(%r14)            ; Overwrite the accumulator
    add         $0x1, 0x70(%r14)             ; Increment the counter
    
    mov         $EG(vm_interrupt), %rax      ; Check for interrupts
    cmp         $0x0, (%rax)
    jz          .L1                          ; Back to the loop head.
    jmp         jit$$trace_exit_4            ; Returns here if inerrupted.

.L4:
    ; Some sort of lazy evaluated state?
    mov         $0x7fd38c094740, %rdi
    mov         $zend_jit_init_func_run_time_cache_helper, %rax
    call        *%rax
    mov         0x40(%r14), %rcx
    mov         %rax, 0x8(%rcx)
    jmp         .L2
    
.L5:
    ; This seems to be our PHP type checking logic here for our "call" to scale().
    lea         0x50(%r14), %rdi
    mov         $0x7fd38c094838, %rax
    mov         %rax, (%r14)
    mov         $0x7fd38c0948b8, %rsi
    mov         $zend_jit_verify_arg_slow, %rax
    call        *%rax
    test        %al, %al
    jnz         .L3                    ; All good, on to business
    
    jmp         JIT$$exception_handler ; For when we pass the wrong parameter type.

```
There is a _lot_ more going on here! The first thing to note, is that we do not see a specific label for our `scale()` function, the logic for which was at label L3. Looking at the overall arrangement of the code we can see that:

- The small `scale()` function is, from a code flow perspective, inlined. Win!
- The inlining does not appear to be the result of analysis of the usage of the function, rather as a side effect of the fact we are using full "tracing" mode in the JIT:
    - In this case, the entire sequence of PHP Opcodes that are called are processed together so the explicit function call to `scale()` isn't a standout, it's just another opcode in a sequence.

However, we can also see a lot of issues here too:

- The code has ballooned enormously in size and is quite branchy:
    - Conditionally the code at L4 is called, likely some lazy-initialised stuff.
    - Most of the time, we are executing the following code blocks:
    - .L1 > .L2 > (.L5 ?) .L3 > repeat
        - It is unclear if .L5 is called from .L2 on every call of if it's checked only on the first iteration based on usage.
        - The purpose of .L2 is not entirely clear but looks like it could be to record the state of the stack in case an exception is triggered at .L5
- Many variables are no longer register allocated.
- Not treating the function call as "special" leads to a lot of stack manipulation in which all the function call setup and return drudgery is still performed and the only thing we lack is a `call` operation in the output assembler:
    - As we have seen in the native tests, the overhead of this operation is actually very small.

### Inference

There is _much_ to be improved for PHP function calls in the JIT exceution path:

- If the basic stack manipulation could be avoided, there would be a significant reduction of the code size and much greater potential to register allocate temporaries.
- Inlining of small functions at the Opcode generation level would significantly reduce PHP function call count, especially in code where there are many basic getter and setter type calls.
- However, this would be a significant change to the Opcode generation layer that would likely confuse most existing debugging tools.


