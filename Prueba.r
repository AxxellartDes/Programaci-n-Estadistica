library(bcv)
help(bcv)

x<-10
y<-20
z<-2026

# print(x+y)

rm(list=ls(all=TRUE)) #eliminar limpiar memoria

# print(x)

z<-c(2,4,7,3,8,9) 
# 1:7 
# print(seq(0,20, by=2.4) )
# seq(0, 20, length=4 
print(help(seq, help_type="html"))
# rep(1:3, times=3) 
# rep(1:3, each=3) 
# rnorm(5, 3, 2)  #para aleatorios - desvicion 
# rnorm(5, sd=2, mean=3) 
# rnorm(5, mean=3, sd=2) 
# runif(5) 
# set.speed(123)
# print(rexp(n = 5, rate = 1/4))
m<- matrix(rnorm(9),3,3);
print(m)