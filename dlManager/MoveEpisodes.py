#!/usr/bin/python
# -*- coding: utf-8 -*-
"""
# Name: MoveEpisodes
# What: Move and sort your episodes from your download folder to the right folder (eg. "/<show name>/Saison x")
# Why: 'Cause it's better when it's automatic
"""

import platform
import os
import shutil
import string

dlPath = '' # Your download folder
tvPath = ''  # Your "Shows" folder

if len(dlPath) == 0 or len(tvPath) == 0:
    print "Pour automatiser, veuillez entrer les dossiers de départ et d'arrivée dans le script (vars 'dlPath' & 'tvPath')\n"
    dlPath = raw_input("Entrer le dossier de départ (ex. /Downloads) : ")
    tvPath = raw_input("Entrer le dossier d'arrivée (ex. /Shows) : ")

# Create listFile containing the show name, show season (according to the file), and the filename
listFile = []
for f in os.listdir(dlPath):
    nick = f.lower()
    if '.s0' in nick:
        listPart = nick.partition('.s0')
        season = listPart[2][:1]
    elif '.s1' in nick:
        listPart = nick.partition('.s1')
        season = '1'+listPart[2][:1]
    if '.s0' in nick or '.s1' in nick:
        nick = listPart[0]
        name = nick.replace('.', ' ')
        name = string.capwords(name)
        listFile.append([name, season, f])

# Move the files at the right place reading listFile
listMove = []
listDir = os.listdir(tvPath)
for name, season, filename in listFile:
# name is the the show name (used to create the "/<show name>" folder)
# season is the show season (according to the file)
# filename is the filename
    tvNamePath = os.path.join(tvPath, name)
    seasonPath = os.path.join(tvNamePath, 'Saison '+season)

    if name in listDir:
        listSubDir = os.listdir(tvNamePath)
        if not 'Saison '+season in listSubDir:
            os.makedirs(seasonPath)
    else:
         os.makedirs(tvNamePath)
         os.makedirs(seasonPath)

    shutil.move(os.path.join(dlPath, filename), os.path.join(seasonPath, filename))
    listMove.append(filename)

# Display the result
print 'Au départ dans %s :' % dlPath
if not listFile:
    print 'Aucun fichier "déplaçable"\n'
else:
    print len(listFile),'fichier(s) "déplaçable(s)" dans le dossier\n'

print "A l'arrivée dans %s :" % tvPath
if not listMove:
    print "Aucun fichier n'a été déplacé"
else:
    print len(listMove),'fichier(s) déplacé(s) :'
    for fiilename in listMove.sort():
        print filename
