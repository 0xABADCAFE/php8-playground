## JIT Analysis 1

The PHP code tested in the inline case was:

```php
function accumulate(int $iMax) : float {
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += 0.001 * $i;
    }
    return $fAcum;
}
```

The loop was been intentionally designed to make unrolling difficult to make the generated code easier to understand. The first step is for PHP to convert the above source code into _PHP Opcode_ which is the low-level bytecode that is cached and executed by Zend Engine.


Using the awesome [Vulcan Logic Dumper](https://github.com/derickr/vld) tool, we can see what this intermim opcode looks like:

```
function name:  accumulate
number of ops:  10
compiled vars:  !0 = $iMax, !1 = $fAcum, !2 = $i
line      #* E I O op                           fetch          ext  return  operands
-------------------------------------------------------------------------------------
   11     0  E >   RECV                                             !0
   12     1        QM_ASSIGN                                        !1      0
   14     2        QM_ASSIGN                                        !2      1
          3      > JMP                                                      ->7
   15     4    >   MUL                                              ~3      !2, 0.001
          5        ADD                                              !1      !1, ~3
   14     6        PRE_INC                                                  !2
          7    >   IS_SMALLER                                               !2, !0
          8      > JMPNZ                                                    ~3, ->4
   17     9    > > RETURN                                                   !1

```

The first thing to note is that the above code is very much a 1:1 transcription of the source. The following variable allocations are in effect:

- !0 contains `$iMax`
- !1 contains `$fAcum`
- !2 contains `$i`


This opcode is translated to native code. We expect to see:

- Sensible register allocation for temporaries.
- A basic loop test and exit.
- Counter increment.
- Counter conversion to double.
- Multiply and accumulation.
- Sundry interrupt handling.

The actual generated assembler for the above inline test is shown below. Comments added for clarity:

```asm
TRACE-1$accumulate$13:
    mov    $EG(jit_trace_num), %rax  ; Preamble
    mov    $0x1, (%rax)

    ; %r14 contains call frame data
    mov    0x50(%r14), %rcx          ; %rcx contains $iMax
    vmovsd 0x60(%r14), %xmm2         ; %xmm2 contains double accumulator $fAcum
    mov    0x70(%r14), %rdx          ; %rdx contains $i

.L1:                                 ; Loop: 11 instructions, 3 memory accesses
    cmp %rcx, %rdx                   ; Compare $iMax and $i...
    jge jit$$trace_exit_0            ;    Exit if $i >= $iMax

    vxorps    %xmm0, %xmm0, %xmm0    ; XOR hack to zero out %xmm0, use as $fTemp
    vcvtsi2sd %rdx,  %xmm0, %xmm0    ; Cast $i to double precision in $fTemp

    mov $0x7f4738e9f828, %rax        ; Loads resolved address of literal 0.001 into %rax
    vmulsd (%rax), %xmm0, %xmm0      ; $fTemp = 0.001 * $fTemp
    vaddsd %xmm0, %xmm2, %xmm2       ; $fAcum += $fTemp

    add $0x1, %rdx                   ; $i++
    mov $EG(vm_interrupt), %rax      ; Test for user break or other interrupt conditions
    cmp $0x0, (%rax)                 ; Interrupt data at (%rax) ?
    jz .L1                           ;    If not, iterate
    jmp jit$$trace_exit_1            ;    Otherwise, exit with interrupted case
```

Note: 0x7f4738e9f828 was the runtime resolved address of the literal double 0.001 during this run and has no other special significance.


The initial conclusion is that the above code is quite suboptimal:

- The literal was not assigned to a temporary for use within the loop.
- Each iteration reloaded the address into %rax and forced the use of an indirect addressing mode operand in the subsequent multiply.
- Uncessesary clobbering of registers,

An improved output could be:

```asm
TRACE-1$accumulate$13:
    mov    $EG(jit_trace_num), %rax
    mov    $0x1, (%rax)
    mov    0x50(%r14), %rcx
    vmovsd 0x60(%r14), %xmm2
    mov    0x70(%r14), %rdx

    ; Loop invariants here
    mov    $0x7f4738e9f828, %rax     ; Resolved address of literal 0.001
    vmovsd (%rax), %xmm1             ; %xmm1 contains 0.001
    mov    $EG(vm_interrupt), %rax   ; %rax not clobbered past here

.L1:                                 ; Loop: 9 instuctions, 1 memory access
    cmp %rcx, %rdx
    jge jit$$trace_exit_0

    vxorps    %xmm0, %xmm0, %xmm0    ; All register to register here
    vcvtsi2sd %rdx,  %xmm0, %xmm0
    vmulsd    %xmm1, %xmm0, %xmm0

    vaddsd    %xmm0, %xmm2, %xmm2
    add       $0x1, %rdx
    cmp       $0x0, (%rax)
    jz        .L1
    jmp       jit$$trace_exit_1
```

### Inference

The above output implies the translation of _PHP Opcode_ to native code follows a relatively straightforward path in which specific opcodes map to a template of assembler that is parameterised with register allocation and other compile paramters and then emitted wholesale. For example:

```
line      #* E I O op                           fetch          ext  return  operands
-------------------------------------------------------------------------------------
   15     4    >   MUL                                              ~3      !2, 0.001   
```

Appears to correspond directly to the following sequence:

```asm
    vxorps    %xmm0, %xmm0, %xmm0
    vcvtsi2sd %rdx,  %xmm0, %xmm0
    mov       $0x7f4738e9f828, %rax
    vmulsd    (%rax), %xmm0, %xmm0
```

Modifying the original function, we can test this assumption:

```php
function accumulate(int $iMax) : float {
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += 0.0005 * $i;
        $fAcum += 0.0005 * $i;
    }
    return $fAcum;
}

```

This results in the following opcode:
```
number of ops:  12
compiled vars:  !0 = $iMax, !1 = $fAcum, !2 = $i
line      #* E I O op                           fetch          ext  return  operands
-------------------------------------------------------------------------------------
   11     0  E >   RECV                                             !0      
   12     1        QM_ASSIGN                                        !1      0
   14     2        QM_ASSIGN                                        !2      1
          3      > JMP                                                      ->9
   15     4    >   MUL                                              ~3      !2, 0.0005
          5        ADD                                              !1      !1, ~3
   16     6        MUL                                              ~3      !2, 0.0005
          7        ADD                                              !1      !1, ~3
   14     8        PRE_INC                                                  !2
          9    >   IS_SMALLER                                               !2, !0
         10      > JMPNZ                                                    ~3, ->4
   18    11    > > RETURN                                                   !1

```

And this in turn JIT compiles to:

```asm
TRACE-1$accumulate$14: ; (unknown)
    mov       $EG(jit_trace_num), %rax
    mov       $0x1, (%rax)
    mov       0x50(%r14), %rcx
    vmovsd    0x60(%r14), %xmm2
    mov       0x70(%r14), %rdx

.L1:
    cmp       %rcx, %rdx
    jge       jit$$trace_exit_0

    ; 15     4    >   MUL                                              ~3      !2, 0.0005
    vxorps    %xmm0, %xmm0, %xmm0
    vcvtsi2sd %rdx,  %xmm0, %xmm0
    mov       $0x7fe7e7a94828, %rax
    vmulsd    (%rax), %xmm0, %xmm0

    vaddsd    %xmm0, %xmm2, %xmm1

    ; 16     6        MUL                                              ~3      !2, 0.0005
    vxorps    %xmm0, %xmm0, %xmm0
    vcvtsi2sd %rdx,  %xmm0, %xmm0
    mov       $0x7fe7e7a94828, %rax
    vmulsd    (%rax), %xmm0, %xmm0
 
    vaddsd    %xmm0, %xmm1, %xmm2
    add       $0x1, %rdx
    mov       $EG(vm_interrupt), %rax
    cmp       $0x0, (%rax)
    jz        .L1
    jmp       jit$$trace_exit_1

```

We can clearly see that the effort in translating the address of the literal 0.0005 is duplicated here.

### Go faster, damnit!

Given what we have seen above, it is clear that to make the JIT do less work, we need to have better opcode to start with. That in turn implies we need to approach our code differently. It is clear that literals are always evaluated via a dereference codepath, whereas variables undergo register allocation. This leads to an obvious optimisation suggestion:

```php
function accumulate(int $iMax) : float {
    $fScale = 0.001; // Is this clever?
    $fAcum  = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += $fScale * $i;
    }
    return $fAcum;
}
```

The answer to the comment is: No. The PHP Opcace code generator defeats this by recognising that `$fScale` is an invariant reference to a literal, folds it and emits _exactly_ the same opcode as before. Which is perfect for interpretive execution but bad for JIT. We can be more underhand, however:

```php
function accumulate(int $iMax, float $fScale = 0.001) : float {
    $fAcum  = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += $fScale * $i;
    }
    return $fAcum;
}

```

This time, the PHP Opcache generator cannot guarantee every invocation of this will not change the literal value. The generated opcode becomes:

```
function name:  accumulate
number of ops:  11
compiled vars:  !0 = $iMax, !1 = $fScale, !2 = $fAcum, !3 = $i
line      #* E I O op                           fetch          ext  return  operands
-------------------------------------------------------------------------------------
   11     0  E >   RECV                                             !0      
          1        RECV_INIT                                        !1      0.001
   12     2        QM_ASSIGN                                        !2      0
   13     3        QM_ASSIGN                                        !3      1
          4      > JMP                                                      ->8
   14     5    >   MUL                                              ~4      !1, !3
          6        ADD                                              !2      !2, ~4
   13     7        PRE_INC                                                  !3
          8    >   IS_SMALLER                                               !3, !0
          9      > JMPNZ                                                    ~4, ->5
   16    10    > > RETURN                                                   !2

```

This looks promising, `$fScale` is now a variable. The JIT compiled assembly language becomes:

```asm
TRACE-1$accumulate$13:
    mov       $EG(jit_trace_num), %rax
    mov       $0x1, (%rax)
    mov       0x50(%r14), %rcx
    vmovsd    0x60(%r14), %xmm3 ; $fScale
    vmovsd    0x70(%r14), %xmm2
    mov       0x80(%r14), %rdx

.L1:
    cmp       %rcx, %rdx
    jge       jit$$trace_exit_0

    ; 14     5    >   MUL                                              ~4      !1, !3
    vxorps    %xmm0, %xmm0, %xmm0
    vcvtsi2sd %rdx,  %xmm0, %xmm0
    vmulsd    %xmm3, %xmm0, %xmm0 ; No memory accesses

    vaddsd    %xmm0, %xmm2, %xmm2
    add       $0x1, %rdx
    mov       $EG(vm_interrupt), %rax
    cmp       $0x0, (%rax)
    jz       .L1
    jmp      jit$$trace_exit_1

```

This looks a lot more like our suggested assembler, but is still suboptimal as it needlessly reloads %rax to check for user interruption every time but we have also removed several instructions from the loop. Unsurprisingly, this in turn runs faster:
- User time: 2.33 seconds, compared to 2.94 seconds for the original version.
- 26.2% peformance gain.

There is no immediate way to fix the continuous %rax clobbering that forms part of the interrupt checking. However:
- We can see it is executed only once per loop.
- Manually unrolling loops would increase the amount of work done per interrupt check.
- Only really sensible for very short loops like this one.
- 